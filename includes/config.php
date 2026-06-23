<?php
// Auto-detect project base URL — works offline (XAMPP) and online (subdirectory)
$doc_root     = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$project_root = str_replace('\\', '/', dirname(__DIR__));
$base_url     = rtrim(str_replace($doc_root, '', $project_root), '/') . '/';
$asset_path   = $base_url . 'assets/sb-admin2/';
