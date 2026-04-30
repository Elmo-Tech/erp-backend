<?php

namespace Tests\Unit\Services;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectWiki;
use App\Modules\Settings\Models\User;
use App\Modules\Project\Actions\ProjectWikiAction;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Property-Based Tests for ProjectWikiAction
 *
 * Feature: project-management-wiki
 * Tests encryption/decryption functionality for project wikis
 */
class WikiServicePropertyTest extends TestCase
{
    use RefreshDatabase, TestTrait;

    private ProjectWikiAction $wikiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wikiService = new ProjectWikiAction;
    }

    /**
     * Property 3: Wiki Encryption Round Trip
     *
     * For any wiki content string, encrypting it via ProjectWikiAction (with isEncrypted=true)
     * and then retrieving it via getDecryptedWiki SHALL return the original content unchanged.
     *
     * **Validates: Requirements 4.2, 4.3, 4.5**
     */
    public function test_wiki_encryption_round_trip(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\string(), // random content string
                Generator\suchThat(
                    fn ($s) => strlen($s) > 0 && strlen($s) <= 255,
                    Generator\string()
                ), // title (non-empty, max 255 chars)
                Generator\choose(1, 100) // random seed for unique identifiers
            )
            ->then(function ($content, $title, $seed) {
                // Skip empty content as it's a trivial case
                if (empty($content)) {
                    $content = 'Default content '.$seed;
                }

                // Ensure title is not empty
                if (empty($title)) {
                    $title = 'Wiki Title '.$seed;
                }

                // Create a user
                $user = User::factory()->create([
                    'name' => 'Wiki Test User '.$seed.'_'.uniqid(),
                    'email' => 'wikiuser'.$seed.'_'.uniqid().'@example.com',
                ]);

                $this->actingAs($user);
                $client = $this->ensureClientProfile($user);

                // Create a project
                $project = Project::factory()->create([
                    'client_id' => $client->id,
                ]);

                // Create wiki with encryption enabled
                $wiki = $this->wikiService->createWiki($project, [
                    'title' => $title,
                    'content' => $content,
                    'isEncrypted' => true,
                ]);

                // Verify wiki was created
                $this->assertInstanceOf(ProjectWiki::class, $wiki,
                    'createWiki should return a ProjectWiki instance');

                // Verify is_encrypted flag is set
                $this->assertTrue($wiki->is_encrypted,
                    'Wiki should be marked as encrypted');

                // Retrieve the wiki using getDecryptedWiki
                $decryptedData = $this->wikiService->getDecryptedWiki($wiki);

                // Verify the decrypted content matches the original
                $this->assertEquals($content, $decryptedData['content'],
                    'Decrypted content should match original content (round trip)');

                // Verify other fields are correct
                $this->assertEquals($wiki->id, $decryptedData['id'],
                    'Wiki ID should match');
                $this->assertEquals($project->id, $decryptedData['projectId'],
                    'Project ID should match');
                $this->assertEquals($title, $decryptedData['title'],
                    'Title should match');
                $this->assertTrue($decryptedData['isEncrypted'],
                    'isEncrypted flag should be true');

                // Verify the model's decrypted_content accessor also works
                $this->assertEquals($content, $wiki->decrypted_content,
                    'Model accessor should return decrypted content');

                // Verify the wiki is persisted in database
                $this->assertDatabaseHas('project_wikis', [
                    'id' => $wiki->id,
                    'project_id' => $project->id,
                    'title' => $title,
                    'is_encrypted' => true,
                ]);
            });
    }

    /**
     * Property 3: Wiki Encryption Round Trip - Update Scenario
     *
     * For any wiki entry, updating the content with encryption enabled and then
     * retrieving it SHALL return the updated content unchanged.
     *
     * **Validates: Requirements 4.2, 4.3, 4.5**
     */
    public function test_wiki_encryption_round_trip_on_update(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\string(), // original content
                Generator\string(), // updated content
                Generator\choose(1, 100) // random seed
            )
            ->then(function ($originalContent, $updatedContent, $seed) {
                // Ensure content is not empty
                if (empty($originalContent)) {
                    $originalContent = 'Original content '.$seed;
                }
                if (empty($updatedContent)) {
                    $updatedContent = 'Updated content '.$seed;
                }

                // Create a user
                $user = User::factory()->create([
                    'name' => 'Wiki Update User '.$seed.'_'.uniqid(),
                    'email' => 'wikiupdate'.$seed.'_'.uniqid().'@example.com',
                ]);

                $this->actingAs($user);
                $client = $this->ensureClientProfile($user);

                // Create a project
                $project = Project::factory()->create([
                    'client_id' => $client->id,
                ]);

                // Create wiki with encryption enabled
                $wiki = $this->wikiService->createWiki($project, [
                    'title' => 'Test Wiki '.$seed,
                    'content' => $originalContent,
                    'isEncrypted' => true,
                ]);

                // Update the wiki with new content (still encrypted)
                $updatedWiki = $this->wikiService->updateWiki($wiki, [
                    'content' => $updatedContent,
                    'isEncrypted' => true,
                ]);

                // Retrieve the updated wiki
                $decryptedData = $this->wikiService->getDecryptedWiki($updatedWiki);

                // Verify the decrypted content matches the updated content
                $this->assertEquals($updatedContent, $decryptedData['content'],
                    'Decrypted content should match updated content after update');

                // Verify is_encrypted flag is still set
                $this->assertTrue($decryptedData['isEncrypted'],
                    'Wiki should still be marked as encrypted after update');
            });
    }

    /**
     * Property 3: Wiki Encryption Round Trip - Non-Encrypted Content
     *
     * For any wiki content stored without encryption, retrieving it via
     * getDecryptedWiki SHALL return the original content unchanged.
     *
     * **Validates: Requirements 4.2, 4.3, 4.5**
     */
    public function test_wiki_non_encrypted_round_trip(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\string(), // content
                Generator\choose(1, 100) // random seed
            )
            ->then(function ($content, $seed) {
                // Ensure content is not empty
                if (empty($content)) {
                    $content = 'Non-encrypted content '.$seed;
                }

                // Create a user
                $user = User::factory()->create([
                    'name' => 'Non-Enc User '.$seed.'_'.uniqid(),
                    'email' => 'nonenc'.$seed.'_'.uniqid().'@example.com',
                ]);

                $this->actingAs($user);
                $client = $this->ensureClientProfile($user);

                // Create a project
                $project = Project::factory()->create([
                    'client_id' => $client->id,
                ]);

                // Create wiki WITHOUT encryption
                $wiki = $this->wikiService->createWiki($project, [
                    'title' => 'Non-Encrypted Wiki '.$seed,
                    'content' => $content,
                    'isEncrypted' => false,
                ]);

                // Verify is_encrypted flag is false
                $this->assertFalse($wiki->is_encrypted,
                    'Wiki should not be marked as encrypted');

                // Retrieve the wiki
                $decryptedData = $this->wikiService->getDecryptedWiki($wiki);

                // Verify the content matches the original (no encryption/decryption needed)
                $this->assertEquals($content, $decryptedData['content'],
                    'Content should match original for non-encrypted wiki');

                // Verify is_encrypted flag is false in response
                $this->assertFalse($decryptedData['isEncrypted'],
                    'isEncrypted flag should be false');
            });
    }

    /**
     * Property 4: Encrypted Content Not Stored in Plaintext
     *
     * For any wiki entry created with isEncrypted=true, the raw content stored
     * in the database SHALL NOT equal the original plaintext content.
     *
     * **Validates: Requirements 4.2**
     */
    public function test_encrypted_content_not_stored_in_plaintext(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\suchThat(
                    fn ($s) => strlen($s) >= 5, // Ensure content is long enough to be meaningful
                    Generator\string()
                ),
                Generator\choose(1, 100) // random seed
            )
            ->then(function ($content, $seed) {
                // Ensure content is not empty and has meaningful length
                if (empty($content) || strlen($content) < 5) {
                    $content = 'Sensitive content that should be encrypted '.$seed;
                }

                // Create a user
                $user = User::factory()->create([
                    'name' => 'Plaintext Test User '.$seed.'_'.uniqid(),
                    'email' => 'plaintext'.$seed.'_'.uniqid().'@example.com',
                ]);

                $this->actingAs($user);
                $client = $this->ensureClientProfile($user);

                // Create a project
                $project = Project::factory()->create([
                    'client_id' => $client->id,
                ]);

                // Create wiki with encryption enabled
                $wiki = $this->wikiService->createWiki($project, [
                    'title' => 'Encrypted Wiki '.$seed,
                    'content' => $content,
                    'isEncrypted' => true,
                ]);

                // Get the raw content from the database (not through accessor)
                $rawWiki = ProjectWiki::find($wiki->id);
                $rawContent = $rawWiki->getAttributes()['content'];

                // Verify the raw content is NOT equal to the original plaintext
                $this->assertNotEquals($content, $rawContent,
                    'Raw database content should NOT equal original plaintext when encrypted');

                // Verify the raw content looks like encrypted data (Laravel Crypt format)
                // Laravel encrypted strings are base64 encoded and contain specific markers
                $this->assertNotEmpty($rawContent,
                    'Encrypted content should not be empty');

                // Verify we can still decrypt it correctly
                $decryptedContent = Crypt::decryptString($rawContent);
                $this->assertEquals($content, $decryptedContent,
                    'Decrypting raw content should return original plaintext');

                // Verify the model's decrypted_content accessor returns the original
                $this->assertEquals($content, $rawWiki->decrypted_content,
                    'Model accessor should return original plaintext');
            });
    }

    /**
     * Property 4: Encrypted Content Not Stored in Plaintext - Database Verification
     *
     * For any wiki entry with isEncrypted=true, querying the database directly
     * SHALL NOT reveal the plaintext content.
     *
     * **Validates: Requirements 4.2**
     */
    public function test_encrypted_content_not_in_database_plaintext(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\choose(1, 100) // random seed
            )
            ->then(function ($seed) {
                // Use a unique, identifiable content string
                $content = 'UNIQUE_PLAINTEXT_MARKER_'.$seed.'_'.uniqid();

                // Create a user
                $user = User::factory()->create([
                    'name' => 'DB Check User '.$seed.'_'.uniqid(),
                    'email' => 'dbcheck'.$seed.'_'.uniqid().'@example.com',
                ]);

                $this->actingAs($user);
                $client = $this->ensureClientProfile($user);

                // Create a project
                $project = Project::factory()->create([
                    'client_id' => $client->id,
                ]);

                // Create wiki with encryption enabled
                $wiki = $this->wikiService->createWiki($project, [
                    'title' => 'DB Check Wiki '.$seed,
                    'content' => $content,
                    'isEncrypted' => true,
                ]);

                // Verify the plaintext content is NOT stored in the database
                $this->assertDatabaseMissing('project_wikis', [
                    'id' => $wiki->id,
                    'content' => $content, // This should NOT match
                ]);

                // Verify the wiki exists with encrypted content
                $this->assertDatabaseHas('project_wikis', [
                    'id' => $wiki->id,
                    'is_encrypted' => true,
                ]);

                // Double-check by querying directly
                $dbWiki = \DB::table('project_wikis')->where('id', $wiki->id)->first();
                $this->assertNotEquals($content, $dbWiki->content,
                    'Database content should not equal plaintext');
                $this->assertStringNotContainsString($content, $dbWiki->content,
                    'Database content should not contain plaintext substring');
            });
    }

    /**
     * Property 4: Non-Encrypted Content IS Stored in Plaintext
     *
     * For any wiki entry created with isEncrypted=false, the raw content stored
     * in the database SHALL equal the original content (no encryption applied).
     *
     * **Validates: Requirements 4.2**
     */
    public function test_non_encrypted_content_stored_in_plaintext(): void
    {
        $this
            ->limitTo(3)
            ->forAll(
                Generator\string(),
                Generator\choose(1, 100) // random seed
            )
            ->then(function ($content, $seed) {
                // Ensure content is not empty
                if (empty($content)) {
                    $content = 'Non-encrypted plaintext '.$seed;
                }

                // Create a user
                $user = User::factory()->create([
                    'name' => 'Non-Enc DB User '.$seed.'_'.uniqid(),
                    'email' => 'nonencdb'.$seed.'_'.uniqid().'@example.com',
                ]);

                $this->actingAs($user);
                $client = $this->ensureClientProfile($user);

                // Create a project
                $project = Project::factory()->create([
                    'client_id' => $client->id,
                ]);

                // Create wiki WITHOUT encryption
                $wiki = $this->wikiService->createWiki($project, [
                    'title' => 'Non-Encrypted DB Wiki '.$seed,
                    'content' => $content,
                    'isEncrypted' => false,
                ]);

                // Verify the plaintext content IS stored in the database
                $this->assertDatabaseHas('project_wikis', [
                    'id' => $wiki->id,
                    'content' => $content, // This SHOULD match for non-encrypted
                    'is_encrypted' => false,
                ]);

                // Get raw content and verify it equals original
                $rawWiki = ProjectWiki::find($wiki->id);
                $rawContent = $rawWiki->getAttributes()['content'];
                $this->assertEquals($content, $rawContent,
                    'Raw database content should equal original for non-encrypted wiki');
            });
    }
}
