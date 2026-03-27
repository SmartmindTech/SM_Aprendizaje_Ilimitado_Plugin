<?php
/**
 * CLI script to set course overview images from Picsum (Lorem Picsum).
 * Run inside the Moodle container.
 *
 * Usage: php /var/www/html/local/sm_graphics_plugin/scripts/set_course_images.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');

$courses = $DB->get_records_sql("SELECT id, fullname FROM {course} WHERE id > 1 ORDER BY id");

if (empty($courses)) {
    echo "No courses found.\n";
    exit(0);
}

// Picsum image IDs that look good for education/courses.
// These are specific high-quality photos from picsum.photos.
$image_ids = [
    237, // laptop on desk
    180, // abstract colorful
    366, // workspace
    403, // nature green
    433, // desk setup
    493, // books
    535, // modern architecture
    593, // colorful abstract
];

$fs = get_file_storage();
$i = 0;

foreach ($courses as $course) {
    $context = context_course::instance($course->id);
    $imageid = $image_ids[$i % count($image_ids)];
    $i++;

    // Download image from Picsum.
    $url = "https://picsum.photos/id/{$imageid}/800/400.jpg";
    echo "Course {$course->id} ({$course->fullname}): downloading image {$imageid}... ";

    $tempfile = tempnam(sys_get_temp_dir(), 'course_img_');
    $imgdata = @file_get_contents($url);

    if ($imgdata === false) {
        // Fallback: use the random endpoint.
        $url = "https://picsum.photos/800/400?random={$i}";
        $imgdata = @file_get_contents($url);
    }

    if ($imgdata === false) {
        echo "FAILED to download.\n";
        continue;
    }

    file_put_contents($tempfile, $imgdata);

    // Delete existing overviewfiles for this course.
    $fs->delete_area_files($context->id, 'course', 'overviewfiles');

    // Store the new image.
    $filerecord = [
        'contextid' => $context->id,
        'component' => 'course',
        'filearea'  => 'overviewfiles',
        'itemid'    => 0,
        'filepath'  => '/',
        'filename'  => "course_{$course->id}.jpg",
    ];

    $fs->create_file_from_pathname($filerecord, $tempfile);
    @unlink($tempfile);

    echo "OK\n";
}

echo "\nDone! Purging caches...\n";
purge_all_caches();
echo "All caches purged.\n";
