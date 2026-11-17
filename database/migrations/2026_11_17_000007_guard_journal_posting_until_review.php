<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P0-04A: fail-closed journal write boundary. This intentionally does not
 * implement posting, reversal, an API, or authorization to move real money.
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('P0-04 journal guards require PostgreSQL 18.');
        }

        DB::unprepared(<<<'SQL'
CREATE FUNCTION public.p004_guard_journal_entry_mutation()
RETURNS trigger LANGUAGE plpgsql
SET search_path = pg_catalog, public
AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NEW.status IS DISTINCT FROM 'draft' OR NEW.posted_at IS NOT NULL THEN
            RAISE EXCEPTION 'P0-04: posting and reversing are disabled pending reviewed command'
                USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    IF TG_OP = 'DELETE' THEN
        IF OLD.status IS DISTINCT FROM 'draft' THEN
            RAISE EXCEPTION 'P0-04: posted journal header is immutable'
                USING ERRCODE = '23514';
        END IF;
        RETURN OLD;
    END IF;

    IF OLD.status IS DISTINCT FROM 'draft'
       OR NEW.status IS DISTINCT FROM 'draft'
       OR NEW.posted_at IS NOT NULL THEN
        RAISE EXCEPTION 'P0-04: posted header is immutable; direct posting is disabled'
            USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
SQL);

        DB::unprepared(<<<'SQL'
CREATE FUNCTION public.p004_guard_journal_line_mutation()
RETURNS trigger LANGUAGE plpgsql
SET search_path = pg_catalog, public
AS $$
DECLARE
    target record;
    parent_status text;
BEGIN
    -- Lock the parent BEFORE touching a line so a future posting transition
    -- and any line edit cannot interleave. If re-parenting, lock by stable key.
    IF TG_OP = 'UPDATE' THEN
        FOR target IN
            SELECT keys.company_id, keys.entry_id FROM (
                SELECT OLD.company_id AS company_id, OLD.entry_id AS entry_id
                UNION
                SELECT NEW.company_id AS company_id, NEW.entry_id AS entry_id
            ) AS keys ORDER BY keys.company_id, keys.entry_id
        LOOP
            SELECT status INTO parent_status
              FROM public.journal_entries
             WHERE company_id = target.company_id AND id = target.entry_id
             FOR UPDATE;
            IF parent_status IS DISTINCT FROM 'draft' THEN
                RAISE EXCEPTION 'P0-04: journal lines are immutable after posting'
                    USING ERRCODE = '23514';
            END IF;
        END LOOP;
        RETURN NEW;
    END IF;

    IF TG_OP = 'INSERT' THEN
        SELECT status INTO parent_status
          FROM public.journal_entries
         WHERE company_id = NEW.company_id AND id = NEW.entry_id
         FOR UPDATE;
        IF parent_status IS DISTINCT FROM 'draft' THEN
            RAISE EXCEPTION 'P0-04: cannot append lines to non-draft journal'
                USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
    END IF;

    SELECT status INTO parent_status
      FROM public.journal_entries
     WHERE company_id = OLD.company_id AND id = OLD.entry_id
     FOR UPDATE;
    IF parent_status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'P0-04: cannot delete lines from non-draft journal'
            USING ERRCODE = '23514';
    END IF;
    RETURN OLD;
END;
$$;
SQL);

        DB::statement('CREATE TRIGGER p004_guard_journal_entries BEFORE INSERT OR UPDATE OR DELETE ON public.journal_entries FOR EACH ROW EXECUTE FUNCTION public.p004_guard_journal_entry_mutation()');
        DB::statement('CREATE TRIGGER p004_guard_journal_lines BEFORE INSERT OR UPDATE OR DELETE ON public.journal_lines FOR EACH ROW EXECUTE FUNCTION public.p004_guard_journal_line_mutation()');
        DB::statement('REVOKE ALL ON FUNCTION public.p004_guard_journal_entry_mutation() FROM PUBLIC');
        DB::statement('REVOKE ALL ON FUNCTION public.p004_guard_journal_line_mutation() FROM PUBLIC');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS p004_guard_journal_lines ON public.journal_lines');
        DB::statement('DROP TRIGGER IF EXISTS p004_guard_journal_entries ON public.journal_entries');
        DB::statement('DROP FUNCTION IF EXISTS public.p004_guard_journal_line_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS public.p004_guard_journal_entry_mutation()');
    }
};
