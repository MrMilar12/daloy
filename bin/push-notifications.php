<?php
// Retained so existing scheduled jobs cannot send alerts after an in-place update.
if (PHP_SAPI !== 'cli') http_response_code(404);
