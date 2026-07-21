<?php

namespace App\Enums;

enum ProposalTemplate: int
{
    case STANDARD = 1;
    case ENTERPRISE = 2;
    case SAAS = 3;
    case RETAINER = 4;

    public function label(): string
    {
        return match ($this) {
            self::STANDARD => 'Standard Proposal',
            self::ENTERPRISE => 'Enterprise Proposal',
            self::SAAS => 'SaaS Proposal',
            self::RETAINER => 'Retainer Proposal',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::STANDARD => 'A concise proposal for general projects and services.',
            self::ENTERPRISE => 'A structured proposal for larger, multi-stakeholder engagements.',
            self::SAAS => 'A subscription-focused proposal for SaaS products and platforms.',
            self::RETAINER => 'An ongoing-services proposal for monthly retainers.',
        };
    }

    /**
     * @return array<int, array{heading: string, body: string}>
     */
    public function sections(): array
    {
        return match ($this) {
            self::STANDARD => [
                ['heading' => 'Proposed Approach', 'body' => 'We will confirm the requirements, deliver the agreed scope in clear stages, and review each milestone with your team.'],
                ['heading' => 'Deliverables', 'body' => 'The engagement includes the agreed implementation, handover documentation, and a final acceptance review.'],
                ['heading' => 'Next Steps', 'body' => 'Approve the proposal, confirm the delivery contacts, and schedule the project kickoff.'],
            ],
            self::ENTERPRISE => [
                ['heading' => 'Executive Alignment', 'body' => 'We begin with stakeholder alignment, success criteria, governance, and an agreed escalation path.'],
                ['heading' => 'Delivery Plan', 'body' => 'Work is delivered through controlled phases with milestone reviews, risk tracking, and documented acceptance.'],
                ['heading' => 'Governance and Support', 'body' => 'Regular steering updates and a named delivery owner keep scope, decisions, and dependencies visible.'],
            ],
            self::SAAS => [
                ['heading' => 'Solution', 'body' => 'The proposed platform combines subscription access, onboarding, configuration, and guided adoption.'],
                ['heading' => 'Implementation', 'body' => 'We configure the workspace, migrate agreed data, train key users, and support the production launch.'],
                ['heading' => 'Subscription and Support', 'body' => 'Ongoing service includes platform access, maintenance, and support under the agreed subscription terms.'],
            ],
            self::RETAINER => [
                ['heading' => 'Monthly Scope', 'body' => 'A reserved monthly capacity covers the agreed operational, advisory, and delivery priorities.'],
                ['heading' => 'Working Cadence', 'body' => 'Priorities are reviewed regularly, with transparent progress updates and a shared backlog.'],
                ['heading' => 'Service Continuity', 'body' => 'The retainer provides predictable access to the team and continuity across ongoing initiatives.'],
            ],
        };
    }
}
