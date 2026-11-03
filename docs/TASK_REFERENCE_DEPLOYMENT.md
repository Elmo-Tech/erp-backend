# Task reference image deployment

Deploy the backend and run the normal non-destructive database migrations before deploying the frontend. This adds the nullable `tasks.reference_images` column and preserves existing cover images.

The application accepts JPEG, PNG and WebP images up to 25 MiB each. Set the actual PHP `upload_max_filesize` to at least 25M and `post_max_size` above 25M, allowing multipart overhead. Set any reverse proxy or platform request-body limit above that value as well. The frontend uploads each reference separately. Verify these limits on the running service; changing Laravel validation alone does not alter PHP or proxy limits.

Ensure the existing public storage disk and `/storage` URL are accessible, and that its persistent volume survives deployments. After deployment, verify with a disposable task and a file larger than 5 MiB, then remove the test task and files according to the normal cleanup process.
