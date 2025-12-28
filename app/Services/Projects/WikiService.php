<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectWiki;
use Illuminate\Support\Facades\Crypt;

class WikiService
{
    /**
     * Create a wiki entry with optional encryption.
     * When isEncrypted is true, the content is encrypted using Laravel's Crypt facade
     * before being stored in the database.
     *
     * @param Project $project The project to create the wiki for
     * @param array $data Wiki data containing:
     *                    - title (string): The wiki entry title
     *                    - content (string): The wiki content (will be encrypted if isEncrypted is true)
     *                    - isEncrypted (bool, optional): Whether to encrypt the content (default: false)
     * @return ProjectWiki The created wiki entry
     */
    public function createWiki(Project $project, array $data): ProjectWiki
    {
        $content = $data['content'];
        $isEncrypted = $data['isEncrypted'] ?? false;

        // Encrypt content if requested
        if ($isEncrypted) {
            $content = Crypt::encryptString($content);
        }

        return ProjectWiki::create([
            'project_id' => $project->id,
            'title' => $data['title'],
            'content' => $content,
            'is_encrypted' => $isEncrypted,
        ]);
    }

    /**
     * Update a wiki entry.
     * Handles encryption/decryption transitions when isEncrypted flag changes.
     *
     * @param ProjectWiki $wiki The wiki entry to update
     * @param array $data Updated wiki data containing:
     *                    - title (string, optional): New title
     *                    - content (string, optional): New content
     *                    - isEncrypted (bool, optional): Whether to encrypt the content
     * @return ProjectWiki The updated wiki entry (fresh from database)
     */
    public function updateWiki(ProjectWiki $wiki, array $data): ProjectWiki
    {
        $updateData = [];

        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }

        if (isset($data['content'])) {
            $content = $data['content'];
            // Use new isEncrypted value if provided, otherwise keep existing
            $isEncrypted = $data['isEncrypted'] ?? $wiki->is_encrypted;

            if ($isEncrypted) {
                $content = Crypt::encryptString($content);
            }

            $updateData['content'] = $content;
            $updateData['is_encrypted'] = $isEncrypted;
        } elseif (isset($data['isEncrypted'])) {
            // If only isEncrypted changed (without new content), re-encrypt/decrypt existing content
            $newIsEncrypted = $data['isEncrypted'];
            
            if ($newIsEncrypted !== $wiki->is_encrypted) {
                // Get the plaintext content (decrypted if currently encrypted)
                $plainContent = $wiki->decrypted_content;
                
                if ($newIsEncrypted) {
                    // Encrypt the content
                    $updateData['content'] = Crypt::encryptString($plainContent);
                } else {
                    // Store as plaintext
                    $updateData['content'] = $plainContent;
                }
                
                $updateData['is_encrypted'] = $newIsEncrypted;
            }
        }

        if (!empty($updateData)) {
            $wiki->update($updateData);
        }

        return $wiki->fresh();
    }

    /**
     * Get wiki with decrypted content.
     * Returns an array with all wiki data, with content automatically decrypted
     * if the wiki entry is marked as encrypted.
     *
     * @param ProjectWiki $wiki The wiki entry to retrieve
     * @return array Array containing:
     *               - id: Wiki ID
     *               - projectId: Associated project ID
     *               - title: Wiki title
     *               - content: Decrypted content
     *               - isEncrypted: Whether the stored content is encrypted
     *               - createdAt: Creation timestamp
     *               - updatedAt: Last update timestamp
     *               - createdBy: User ID who created the entry
     *               - updatedBy: User ID who last updated the entry
     */
    public function getDecryptedWiki(ProjectWiki $wiki): array
    {
        return [
            'id' => $wiki->id,
            'projectId' => $wiki->project_id,
            'title' => $wiki->title,
            'content' => $wiki->decrypted_content,
            'isEncrypted' => $wiki->is_encrypted,
            'createdAt' => $wiki->created_at,
            'updatedAt' => $wiki->updated_at,
            'createdBy' => $wiki->created_by,
            'updatedBy' => $wiki->updated_by,
        ];
    }

    /**
     * Get all wikis for a project with decrypted content.
     *
     * @param Project $project The project to get wikis for
     * @return array Array of wiki data with decrypted content
     */
    public function getProjectWikis(Project $project): array
    {
        return $project->wikis->map(function (ProjectWiki $wiki) {
            return $this->getDecryptedWiki($wiki);
        })->toArray();
    }

    /**
     * Delete a wiki entry.
     *
     * @param ProjectWiki $wiki The wiki entry to delete
     * @return bool True if deletion was successful
     */
    public function deleteWiki(ProjectWiki $wiki): bool
    {
        return $wiki->delete();
    }
}
