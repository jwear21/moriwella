<?php
/* Memory-movie worker. Run from cron every minute:  php /path/to/app/worker.php
   Renders queued movies one at a time. It refuses to run from the web.
   Copyright (c) JSW Software Designs */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/src/lib.php';
require __DIR__ . '/src/photos.php';
require __DIR__ . '/src/movie_render.php';
mv_worker_run();
