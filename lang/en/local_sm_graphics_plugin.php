<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * SM Graphic Layer Plugin - English language strings.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin identity.
$string['pluginname']       = 'SM Graphic Layer';
$string['privacy:metadata'] = 'The SM Graphic Layer plugin does not store any personal data.';

// Admin settings — master toggle.
$string['enabled']          = 'Enable SM Graphic Layer';
$string['enabled_desc']     = 'Turn the visual overlay layer on or off site-wide. When disabled, Moodle renders normally with no changes from this plugin.';

// Admin settings — colors section.
$string['colors_heading']       = 'Brand Colors';
$string['color_primary']        = 'Primary color';
$string['color_primary_desc']   = 'Main brand color used for buttons, links, and accents. Use hex format, e.g. #0f6cbf';
$string['color_header_bg']      = 'Header background color';
$string['color_header_bg_desc'] = 'Background color of the top navigation bar. Use hex format, e.g. #1a1f35';
$string['color_sidebar_bg']     = 'Sidebar background color';
$string['color_sidebar_bg_desc']= 'Background color of the side navigation panel. Use hex format, e.g. #ffffff';

// Admin settings — logo section.
$string['logo_heading']     = 'Logo';
$string['logo_url']         = 'Logo URL';
$string['logo_url_desc']    = 'Full URL to the logo image shown in the header. Leave blank to use the default Moodle site logo.';

// Admin settings — plugin updates.
$string['update_heading']           = 'Plugin Updates';
$string['update_button']            = 'Check for updates';
$string['update_button_desc']       = 'Checks GitHub for newer versions of the plugin and theme.';
$string['update_current_version']   = 'Current version';
$string['update_new_version']       = 'New version';
$string['update_available']         = 'Update available';
$string['update_available_msg']     = 'Update available: {$a->current} &rarr; {$a->new}';
$string['update_uptodate']          = 'Plugin is up to date (v{$a}).';
$string['update_confirm']           = 'This will download and install the latest version of the plugin and the SmartMind theme from GitHub. A Moodle upgrade will be triggered afterwards.';
$string['update_confirm_question']  = 'Do you want to proceed with the update?';
$string['update_success']           = 'Update completed successfully. Click Continue to run the Moodle upgrade.';
$string['update_failed']            = 'Update failed. Please try again or update manually.';
$string['update_downloading']       = 'Downloading update...';
$string['update_downloaded']        = 'Downloaded';
$string['update_installing']        = 'Installing update...';
$string['update_copying']           = 'Copying files...';
$string['update_files_copied']      = 'files copied';
$string['update_caches_purged']     = 'Caches purged';
$string['update_plugin_theme']      = 'Update Plugin + Theme';
$string['update_page_title']        = 'Update SM Graphic Layer';
$string['update_fetch_error']       = 'Could not fetch update information from GitHub.';
$string['update_not_writable']      = 'Directory is not writable';
$string['update_copy_failed']       = 'File copy failed';
$string['update_download_failed']   = 'Download failed';
$string['update_extract_failed']    = 'Failed to extract ZIP file';
$string['update_step_plugin']       = 'Step 1: Updating plugin';
$string['update_step_theme']        = 'Step 2: Updating theme';

// Welcome page.
$string['welcome_title']      = 'Bienvenida';
$string['welcome_heading']    = 'Bienvenido a SmartMind';

// Catalogue categories.
$string['catalogue_category'] = 'Catalogue category';

// Navigation label overrides.
$string['nav_home']       = 'Catalogue';
$string['nav_dashboard']  = 'Espacio personal';
$string['nav_mycourses']  = 'Add course';

// User management page (company managers).
$string['usermgmt_title']             = 'User management';
$string['usermgmt_heading']           = 'User management';
$string['usermgmt_createuser']        = 'Create user';
$string['usermgmt_createuser_desc']   = 'Create a new user account for your company.';
$string['usermgmt_editusers']         = 'Edit users';
$string['usermgmt_editusers_desc']    = 'View and edit existing user profiles.';
$string['usermgmt_deptusers']         = 'Users by department';
$string['usermgmt_deptusers_desc']    = 'Manage user assignment by department.';
$string['usermgmt_uploadusers']       = 'Upload users';
$string['usermgmt_uploadusers_desc']  = 'Bulk upload users from a CSV file.';
$string['usermgmt_bulkdownload']      = 'Bulk user download';
$string['usermgmt_bulkdownload_desc'] = 'Download user data in bulk.';
$string['usermgmt_userlist']          = 'Registered users';
$string['usermgmt_th_name']           = 'Name';
$string['usermgmt_th_email']          = 'Email';
$string['usermgmt_th_lastaccess']     = 'Last access';
$string['usermgmt_th_actions']        = 'Actions';
$string['usermgmt_edit']              = 'Edit';
$string['usermgmt_delete']            = 'Delete';
$string['usermgmt_delete_confirm']    = 'Are you sure you want to delete this user? This action cannot be undone.';
$string['usermgmt_deleted']           = 'User deleted successfully.';
$string['usermgmt_never']             = 'Never';
$string['usermgmt_nousers']           = 'No registered users found in your company.';

// Other management page — category labels (sub-options come from IOMAD lang files).
$string['othermgmt_title']              = 'Other management';
$string['othermgmt_heading']            = 'Other management';
$string['othermgmt_companies']          = 'Companies';
$string['othermgmt_courses']            = 'Courses';
$string['othermgmt_licenses']           = 'Licenses';
$string['othermgmt_competences']        = 'Competences';
$string['othermgmt_reports']            = 'Reports';

// Company student limits (admin page).
$string['companylimits_heading']       = 'Student limits per company';
$string['companylimits_button']        = 'Manage company limits';
$string['companylimits_button_desc']   = 'Set the maximum number of students each company can register.';
$string['companylimits_title']         = 'Student limits per company';
$string['companylimits_th_company']    = 'Company';
$string['companylimits_th_shortname']  = 'Short name';
$string['companylimits_th_students']   = 'Current students';
$string['companylimits_th_maxlimit']   = 'Max. students';
$string['companylimits_th_status']     = 'Status';
$string['companylimits_unlimited']     = 'Unlimited';
$string['companylimits_ok']            = 'OK';
$string['companylimits_full']          = 'Full';
$string['companylimits_save']          = 'Save limits';
$string['companylimits_saved']         = 'Student limits saved successfully.';
$string['companylimits_help']          = 'Set 0 for unlimited students.';
$string['companylimits_field_label']   = 'Maximum active users';

// Upload users page.
$string['uploadusers_title']       = 'Upload users';
$string['uploadusers_subtitle']    = 'Import users from a CSV file to your company.';
$string['uploadusers_file']        = 'Select CSV file';
$string['uploadusers_file_help']   = '.csv format — comma-separated, UTF-8 encoding';
$string['uploadusers_type']        = 'Upload type';
$string['uploadusers_submit']      = 'Upload';
$string['uploadusers_cancel']      = 'Cancel';
$string['uploadusers_nofile']      = 'No file was uploaded. Please select a CSV file.';
$string['uploadusers_empty']       = 'The CSV file is empty or could not be read.';

// User management — limit display.
$string['usermgmt_limit_reached']      = 'Your company has reached the maximum number of students. You cannot create new users until the limit is increased.';
$string['usermgmt_upload_exceeds']     = 'The CSV file contains {$a->csvcount} users, but your company only has {$a->remaining} available slots (limit: {$a->limit}). No users were imported. Reduce the file or request a higher limit.';

// Course pricing.
$string['pricing_header']         = 'Pricing';
$string['pricing_amount']         = 'Price';
$string['pricing_amount_help']    = 'Set the course price. Use 0 for free courses.';
$string['pricing_currency']       = 'Currency';
$string['pricing_error_negative'] = 'The price cannot be negative.';

// Course comments.
$string['comments_title'] = 'Comments';
$string['comments_newest'] = 'Newest';
$string['comments_oldest'] = 'Oldest';
$string['comments_empty'] = 'No comments yet. Be the first to share your opinion!';
$string['comments_load_more'] = 'Load more comments';
$string['comments_post'] = 'Post comment';
$string['comments_post_reply'] = 'Post reply';
$string['comments_write'] = 'Write a comment...';
$string['comments_write_reply'] = 'Write a reply...';
$string['comments_edit'] = 'Edit';
$string['comments_delete'] = 'Delete';
$string['comments_delete_confirm'] = 'Are you sure you want to delete this comment? This action cannot be undone.';
$string['comments_edited'] = 'edited';
$string['comments_reply'] = 'Reply';
$string['comments_replies'] = 'Replies';
$string['comments_search_users'] = 'Search users...';
$string['comments_no_users'] = 'No users found';
$string['comments_just_now'] = 'Just now';
$string['comments_minutes_ago'] = 'min';
$string['comments_hours_ago'] = 'h';
$string['comments_days_ago'] = 'd';
$string['comments_slide'] = 'Slide';
$string['comments_question'] = 'Question';
$string['comments_chapter'] = 'Chapter';
$string['comments_page'] = 'Page';
$string['comments_position'] = 'Position';

// Statistics page.
$string['stats_title']              = 'Statistics';
$string['stats_heading']            = 'Statistics';
$string['stats_active_5days']       = 'Connected (last 5 days)';
$string['stats_courses_started']    = 'Courses started';
$string['stats_courses_completed']  = 'Courses completed';
$string['stats_completion_rate']    = 'Completion rate';
$string['stats_courses_available']  = 'Courses available';
$string['stats_weekly_completions'] = 'Courses completed per week';
$string['stats_weekly_active']      = 'Unique users connected per week';

// New user credentials email.
$string['messageprovider:newusercredentials'] = 'Login credentials for new users';
$string['newuser_email_subject'] = 'Your login credentials — {$a->sitename}';
$string['newuser_email_small']   = 'Your login credentials for {$a->sitename} have been created.';
$string['newuser_email_body']    = 'Hello {$a->firstname},

Your account has been created at {$a->sitename} ({$a->company}).

Your login credentials:
  Username: {$a->username}
  Password: {$a->password}

Log in here: {$a->loginurl}

You will be asked to change your password on your first login.

Best regards,
{$a->sitename} Team';
$string['newuser_email_body_html'] = '<p>Hello <strong>{$a->firstname}</strong>,</p>
<p>Your account has been created at <strong>{$a->sitename}</strong> ({$a->company}).</p>
<table style="border-collapse:collapse;margin:16px 0;width:100%;max-width:400px;">
<tr><td style="padding:10px 16px;background:#f1f5f9;font-weight:600;border-radius:8px 0 0 0;">Username</td>
    <td style="padding:10px 16px;background:#f8fafc;border-radius:0 8px 0 0;">{$a->username}</td></tr>
<tr><td style="padding:10px 16px;background:#f1f5f9;font-weight:600;border-radius:0 0 0 8px;">Password</td>
    <td style="padding:10px 16px;background:#f8fafc;font-family:monospace;letter-spacing:0.05em;border-radius:0 0 8px 0;">{$a->password}</td></tr>
</table>
<p><a href="{$a->loginurl}" style="display:inline-block;padding:12px 28px;background:#10b981;color:#fff;text-decoration:none;border-radius:10px;font-weight:600;font-size:1rem;">Log in to the platform</a></p>
<p style="color:#6b7280;font-size:0.85em;">You will be asked to change your password on your first login.</p>
<p>Best regards,<br>{$a->sitename} Team</p>';

// SMTP configuration (outgoing email).
$string['smtp_heading']       = 'Outgoing Email (SMTP)';
$string['smtp_heading_desc']  = 'Configure the SMTP server for sending emails (user credentials, notifications, etc.).';
$string['smtp_host']          = 'SMTP Server';
$string['smtp_host_desc']     = 'SMTP server and port (e.g. smtp.office365.com:587).';
$string['smtp_security']      = 'SMTP Security';
$string['smtp_security_desc'] = 'Secure connection type for the SMTP server.';
$string['smtp_username']      = 'SMTP Username';
$string['smtp_username_desc'] = 'Email address to authenticate with the SMTP server.';
$string['smtp_password']      = 'SMTP Password';
$string['smtp_password_desc'] = 'Password or app password to authenticate with the SMTP server.';
$string['smtp_noreply']       = 'No-reply address';
$string['smtp_noreply_desc']  = 'Email address shown as the sender in outgoing emails.';

// Capabilities.
$string['sm_graphics_plugin:view'] = 'View SM Graphic Layer';
$string['sm_graphics_plugin:post_comments'] = 'Post course comments';
$string['sm_graphics_plugin:delete_any_comment'] = 'Delete any course comment';

// Course page (Udemy-style player).
$string['course_page_back'] = 'Back to courses';
$string['course_page_learning_route'] = 'Learning path';
$string['course_page_activities_count'] = 'Activities';
$string['course_page_sections'] = 'Sections';
$string['course_page_teachers'] = 'Teachers';
$string['course_page_students'] = 'Students';
$string['course_page_description'] = 'Description';
$string['course_page_grades'] = 'Grades';
$string['course_page_fullscreen'] = 'Fullscreen';
$string['course_page_exit_fullscreen'] = 'Exit fullscreen';
$string['course_page_select_activity'] = 'Select an activity to start';
$string['course_page_prev'] = 'Previous';
$string['course_page_next'] = 'Next';
$string['course_page_complete'] = 'Complete';
$string['course_page_collapse_sidebar'] = 'Collapse sidebar';
$string['course_page_expand_sidebar'] = 'Expand sidebar';
$string['course_page_grade_item'] = 'Activity';
$string['course_page_grade_total'] = 'Course total';
$string['course_page_no_grades'] = 'No grades available yet.';

// Course page counter.
$string['course_page_counter_slide'] = 'Slide';
$string['course_page_counter_page'] = 'Page';
$string['course_page_counter_chapter'] = 'Chapter';
$string['course_page_counter_question'] = 'Question';
$string['course_page_counter_video'] = 'Video';
$string['course_page_video_unsupported'] = 'Your browser does not support the video tag.';

// Focus mode.
$string['focus_mode'] = 'Focus mode';

// Course landing page.
$string['landing_program_content'] = 'Program Content';
$string['landing_course_info']     = 'Course Information';
$string['landing_duration']        = 'Official Duration';
$string['landing_language']        = 'Language';
$string['landing_category']        = 'Category';
$string['landing_modules']         = 'Modules';
$string['landing_sections']        = 'Sections';
$string['landing_enrol']           = 'Enrol';
$string['landing_view_course']     = 'View Course';
$string['course_hours']            = 'Course hours';
$string['course_hours_help']       = 'Duration in hours shown on the course landing page.';
$string['sepe_code']               = 'SEPE Code';
$string['sepe_code_help']          = 'Code from SEPE (Servicio Público de Empleo Estatal).';
$string['course_info_header']      = 'SmartMind Course Information';
$string['course_description']      = 'Course description';
$string['course_category_field']   = 'Course category';
$string['course_category_field_help'] = 'SmartMind training category for this course.';
$string['course_category_none']    = '-- Select --';
$string['smartmind_code']          = 'SmartMind Code';
$string['smartmind_code_help']     = 'SmartMind course identifier.';
$string['course_level']            = 'Level';
$string['course_level_help']       = 'Course difficulty level.';
$string['level_beginner']          = 'Basic';
$string['level_medium']            = 'Intermediate';
$string['level_advanced']          = 'Advanced';
$string['completion_percentage']      = 'Completion percentage';
$string['completion_percentage_help'] = 'Percentage of the course that must be completed to be considered finished (0-100).';
$string['landing_level']              = 'Level';
$string['landing_completion']         = 'Completion';
$string['landing_edit']               = 'Edit Course Settings';
$string['landing_save']               = 'Save';
$string['landing_cancel']             = 'Cancel';
$string['landing_add_activity']       = 'Add Activity';
$string['landing_edit_activity']      = 'Edit activity';
$string['landing_delete_activity']    = 'Delete activity';
$string['landing_delete_confirm']     = 'Are you sure you want to delete this activity? This action cannot be undone.';
$string['landing_activity_type']      = 'Activity type';
$string['landing_activity_name']      = 'Activity name';
$string['landing_activity_url']       = 'URL';
$string['landing_genially_url_hint']  = 'Paste the Genially embed URL (e.g., https://view.genial.ly/...)';
$string['landing_add_redirect']       = 'Standard form';
$string['landing_add_moodle']         = 'Other Activities';
$string['landing_video_upload']       = 'Upload File';
$string['landing_video_upload_hint']  = 'Click or drag a video file here (mp4, webm, ogg, mov...)';
$string['landing_start']              = 'Start Course';
$string['landing_continue']           = 'Continue Course';
$string['landing_next_activity']      = 'Next activity';
$string['landing_unenrol']            = 'Unenrol';
$string['landing_unenrol_confirm_title'] = 'Confirm Unenrolment';
$string['landing_unenrol_confirm']    = 'Are you sure you want to unenrol from this course? Your progress will be lost.';
$string['landing_enrolled_badge']     = 'Enrolled';
$string['landing_back']               = 'Back to home';
$string['landing_continue_learning']  = 'Continue learning';
$string['landing_what_youll_learn']   = 'What you\'ll learn';
$string['landing_content_types']      = 'Content types';
$string['landing_course_content']     = 'Course content';
$string['landing_elements']           = 'elements';
$string['landing_completed_count']    = 'completed';
$string['landing_min']                = 'min';
$string['landing_completed_label']    = 'completed';
$string['landing_of']                 = 'of';
$string['landing_lessons']            = 'lessons';
$string['landing_min_remaining']      = 'min remaining';
$string['landing_cert_included']      = 'Certificate included';
$string['objectives_header']          = 'Learning objectives';
$string['objectives_add']             = 'Add objective';
$string['objectives_placeholder']     = 'Type a learning objective...';
$string['objectives_remove']          = 'Remove';
$string['objectives_drag']            = 'Drag to reorder';
$string['objectives_error_max']       = 'Maximum 20 objectives allowed.';
$string['objectives_restore_hint']    = 'One objective per line';
$string['restore_desc_hint']          = 'Course description shown on the landing page. Will be auto-translated.';
$string['restore_objectives_hint']    = 'Add learning objectives. They will be auto-translated to other languages.';
$string['restore_select_company']     = 'Select a company';
$string['restore_select_all']         = 'Select all';
$string['restore_company']            = 'Company';
$string['restore_company_short']      = 'Short name';
$string['restore_new_course']         = 'Restore as new course';

// Grades & Certificates page.
$string['gradescerts_nav']            = 'Grades & Diplomas';
$string['gradescerts_title']          = 'Grades & Diplomas';
$string['gradescerts_heading']        = 'Grades & Diplomas';
$string['gradescerts_desc']           = 'Diplomas earned upon completing courses. Downloadable and verifiable.';
$string['gradescerts_course']         = 'Course';
$string['gradescerts_grade']          = 'Grade';
$string['gradescerts_progress']       = 'Progress';
$string['gradescerts_certificate']    = 'Diploma';
$string['gradescerts_download']       = 'Download Diploma';
$string['gradescerts_download_all']   = 'Download All';
$string['gradescerts_no_grade']       = 'No grade';
$string['gradescerts_not_available']  = 'Not yet available';
$string['gradescerts_language']       = 'Diploma language';
$string['gradescerts_hours']          = 'hours';
$string['gradescerts_no_courses']     = 'No enrolled courses';
$string['gradescerts_inprogress']     = 'In progress';
$string['gradescerts_official']       = 'Official certificate';
$string['gradescerts_issued']         = 'Issued on';
$string['gradescerts_download_pdf']   = 'Download PDF';

// Certificate verification.
$string['verify_title']         = 'Certificate verification';
$string['verify_heading']       = 'Verify a certificate';
$string['verify_placeholder']   = 'Enter the verification code';
$string['verify_button']        = 'Verify';
$string['verify_student']       = 'Student';
$string['verify_course']        = 'Course';
$string['verify_date']          = 'Completion date';
$string['verify_company']       = 'Company';
$string['verify_code']          = 'Verification code';
$string['verify_success']       = 'Certificate verified successfully';
$string['verify_notfound']      = 'No certificate found with that verification code.';
$string['verify_back_login']    = 'Back to login';

// IOMAD dashboard (SmartMind card view).
$string['iomaddashboard_heading']  = 'Administration';
$string['iomad_configuration']     = 'Configuration';
$string['iomad_users']             = 'Users';
$string['iomad_emailtemplates']    = 'Email templates';
$string['iomad_shop']              = 'Shop';

// Course management page.
$string['nav_coursemanagement']   = 'Course management';
$string['coursemgmt_heading']    = 'Course management';
$string['coursemgmt_create']     = 'Create course';
$string['coursemgmt_create_desc'] = 'Create a new course';
$string['coursemgmt_assign']     = 'Assign to company';
$string['coursemgmt_assign_desc'] = 'Assign courses to your company';
$string['coursemgmt_restore']      = 'Restore course';
$string['coursemgmt_restore_desc'] = 'Restore a course from backup';
$string['coursemgmt_createcat']      = 'Create category';
$string['coursemgmt_createcat_desc'] = 'Create a new course category';

// Create category page.
$string['createcat_title']      = 'Create category';
$string['createcat_name']       = 'Category name';
$string['createcat_image']      = 'Background image';
$string['createcat_image_help'] = 'JPG, PNG or WebP. Recommended size: 600×300 px.';
$string['createcat_sortorder']  = 'Sort order';
$string['createcat_preview']    = 'Card preview';
$string['createcat_submit']     = 'Create category';
$string['createcat_cancel']     = 'Cancel';
$string['createcat_success']    = 'Category created successfully.';

// Manage categories page.
$string['managecat_title']          = 'Manage categories';
$string['managecat_save']           = 'Save changes';
$string['managecat_updated']        = 'Category updated successfully.';
$string['managecat_deleted']        = 'Category deleted successfully.';
$string['managecat_delete_confirm'] = 'Are you sure you want to delete this category? Assigned courses will be unlinked.';
$string['managecat_empty']          = 'No categories found.';
$string['coursemgmt_managecat']      = 'Manage categories';
$string['coursemgmt_managecat_desc'] = 'View and organize course categories';
$string['coursemgmt_sharepoint']      = 'Import from SharePoint';
$string['coursemgmt_sharepoint_desc'] = 'Import a full course from SharePoint';
$string['coursemgmt_companies']    = 'Companies';
$string['coursemgmt_courses_col']  = 'Assigned courses';
$string['coursemgmt_users_col']    = 'Users';

// AI Configuration.
$string['ai_settings_heading']      = 'AI Configuration';
$string['gemini_api_key']           = 'Gemini API Key';
$string['gemini_api_key_desc']      = 'Google Generative AI API key for activity duration estimation. Get one at https://ai.google.dev/';
$string['gemini_model']             = 'Gemini Model';
$string['gemini_model_desc']        = 'AI model name for duration estimation (default: gemma-3-4b-it).';
$string['ai_suggested_duration']    = 'AI-suggested: {$a} hours — you can change this value';
$string['ai_duration_label']        = 'AI-estimated';

// Course player redesign.
$string['course_page_module_content']       = 'Module content';
$string['course_page_mycourses_breadcrumb'] = 'My courses';

// My Courses page.
$string['page_eyebrow']              = 'UNLIMITED LEARNING';
$string['mycourses_title']           = 'My courses';
$string['mycourses_desc']            = 'Manage your learning and continue where you left off.';
$string['mycourses_inprogress']      = 'In progress';
$string['mycourses_completed']       = 'Completed';
$string['mycourses_all']             = 'All';
$string['mycourses_continue']        = 'Continue';
$string['mycourses_review']          = 'Review';
$string['mycourses_completed_label'] = 'completed';
$string['mycourses_resource']        = 'Resource';
$string['mycourses_of']              = 'of';
$string['mycourses_empty']           = 'You have no enrolled courses yet.';
$string['mycourses_nav']             = 'My courses';
$string['catalogue_modules']         = 'modules';

// Profile page.
$string['profile_xp_total']      = 'XP Total';
$string['profile_streak']        = 'Current streak';
$string['profile_contents']      = 'Contents';
$string['profile_hours']         = 'Hours';
$string['profile_streak_keep']   = 'Current streak · Keep it up!';
$string['profile_days_to']       = '{$a->days} days to +{$a->xp} XP';
$string['profile_weekly']        = 'WEEKLY ACTIVITY';
$string['profile_level']         = 'Level {$a}';
$string['profile_xp_remaining']  = '{$a->xp} XP to level {$a->level}';
$string['profile_since']         = 'Since {$a}';

// Update notifications.
$string['task:checkforupdates'] = 'Check for plugin updates';
$string['messageprovider:updatenotification'] = 'Plugin update notifications';
$string['updateavailable_subject'] = 'SmartMind Graphics Plugin update available: v{$a}';
$string['updateavailable_message'] = 'A new version of SmartMind Graphics Plugin is available.

Current version: {$a->currentversion}
New version: {$a->newversion}

To install the update, go to: Site Administration > Plugins > Install plugins and upload the new ZIP.';
$string['updateavailable_message_html'] = '<p>A new version of <strong>SmartMind Graphics Plugin</strong> is available.</p>
<table>
<tr><td><strong>Current version:</strong></td><td>{$a->currentversion}</td></tr>
<tr><td><strong>New version:</strong></td><td>{$a->newversion}</td></tr>
</table>
<p>To install the update, go to <strong>Site Administration &gt; Plugins &gt; Install plugins</strong> and upload the new ZIP.</p>';
$string['updateplugin'] = 'Update SmartMind Plugin';
$string['update_manual_title'] = 'Manual update instructions';
$string['update_manual_step1'] = 'Download the latest ZIP from GitHub:';
$string['update_manual_step2'] = 'Go to the Moodle plugin installer:';
$string['update_manual_step3'] = 'Upload the ZIP and follow the on-screen instructions.';

// SharePoint / Course Loader.
$string['sp_heading']                = 'SharePoint Integration';
$string['sp_courseloader_button']     = 'Open course loader';
$string['sp_courseloader_button_desc'] = 'Automatically import courses from a SharePoint folder.';
$string['sp_tenant_id']              = 'Azure AD Tenant ID';
$string['sp_tenant_id_desc']         = 'Azure Active Directory tenant ID (GUID format).';
$string['sp_client_id']              = 'Azure AD Client ID';
$string['sp_client_id_desc']         = 'Application (client) ID from the Azure AD App Registration.';
$string['sp_client_secret']          = 'Azure AD Client Secret';
$string['sp_client_secret_desc']     = 'Client secret from the App Registration.';
$string['sp_site_url']               = 'SharePoint Site URL';
$string['sp_site_url_desc']          = 'Base URL of the SharePoint site (e.g. https://yourorg.sharepoint.com/sites/LMS).';
$string['courseloader_title']         = 'Course Loader from SharePoint';
$string['courseloader_subtitle']      = 'Paste a SharePoint folder URL to scan its contents and automatically import the course.';
$string['courseloader_folder_url']    = 'SharePoint folder URL';
$string['courseloader_folder_url_placeholder'] = 'https://yourorg.sharepoint.com/sites/LMS/Shared Documents/Courses/COURSE_CODE';
$string['courseloader_category']      = 'Target category';
$string['courseloader_scan']          = 'Scan folder';
$string['courseloader_import']        = 'Import course';
$string['courseloader_scanning']      = 'Scanning SharePoint folder...';
$string['courseloader_importing']     = 'Importing course...';
$string['courseloader_scan_results']  = 'Scan results';
$string['courseloader_file_type']     = 'Type';
$string['courseloader_file_count']    = 'Files';
$string['courseloader_file_names']    = 'Names';
$string['courseloader_warnings']      = 'Warnings';
$string['courseloader_success']       = 'Course imported successfully';
$string['courseloader_go_to_course']  = 'Go to course';
$string['courseloader_error']         = 'Error during import';
$string['courseloader_no_config']     = 'Configure SharePoint credentials in the plugin settings before using the course loader.';
$string['courseloader_course_sp']     = 'Course in SharePoint';
$string['courseloader_search_course'] = 'Search course...';
$string['courseloader_companies']     = 'Target companies';
$string['courseloader_search_company'] = 'Search company...';
$string['courseloader_company']       = 'Company';
$string['courseloader_shortname']     = 'Short name';
$string['courseloader_courses_available'] = '{$a} courses available';
$string['courseloader_synced']        = 'Synced';
$string['courseloader_sync']         = 'Sync';
$string['courseloader_syncing']      = 'Syncing...';
$string['courseloader_sync_done']    = 'Sync completed. Reloading...';
$string['courseloader_select_company'] = 'Select at least one target company.';
$string['courseloader_type_mbz']      = 'Moodle Backup (MBZ)';
$string['courseloader_type_scorm']    = 'SCORM Packages';
$string['courseloader_type_pdf']      = 'PDF Documents';
$string['courseloader_type_documents'] = 'Platform Documents';
$string['courseloader_type_aiken']    = 'AIKEN Evaluations';
$string['courseloader_type_gift']     = 'GIFT Evaluations';
$string['courseloader_step_mbz']      = 'Restoring MBZ backup...';
$string['courseloader_step_scorm']    = 'Configuring SCORM packages with external URL...';
$string['courseloader_step_resources'] = 'Creating linked resources...';
$string['courseloader_step_eval']     = 'Importing evaluations...';
$string['courseloader_import_courses'] = 'Import courses from SharePoint';
