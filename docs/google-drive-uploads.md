# Google Drive uploads

The application accepts Drive uploads up to 100 MiB per file. Deployments must also configure the web server and PHP; otherwise the server can reject a request before Laravel receives it. For example, set PHP `upload_max_filesize = 100M`, `post_max_size = 110M`, `max_input_time = 600`, and `max_execution_time = 600`, and set Nginx `client_max_body_size 110m` plus suitable proxy/FastCGI timeouts. Apply equivalent settings for the actual hosting stack and restart or reload the relevant services. Leave enough disk space for PHP's temporary upload files. Task documents have a separate 25 MiB per-file application limit.

The backend streams an uploaded temporary file to a Google Drive resumable upload session; the file still reaches the application server first. The connected Google account must have permission to add files to the selected folder, with storage quota available. Verify with the real connected account and a video near the limit after deployment.
