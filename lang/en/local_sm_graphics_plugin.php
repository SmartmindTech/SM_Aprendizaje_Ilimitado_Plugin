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
$string['catalogue_category'] = 'Categoría del catálogo';

// Navigation label overrides.
$string['nav_home']       = 'Catálogo';
$string['nav_dashboard']  = 'Espacio personal';
$string['nav_mycourses']  = 'Añadir curso';

// User management page (company managers).
$string['usermgmt_title']             = 'Gestión de usuarios';
$string['usermgmt_heading']           = 'Gestión de usuarios';
$string['usermgmt_createuser']        = 'Crear usuario';
$string['usermgmt_createuser_desc']   = 'Crear una nueva cuenta de usuario para tu empresa.';
$string['usermgmt_editusers']         = 'Editar usuarios';
$string['usermgmt_editusers_desc']    = 'Ver y editar los perfiles de usuarios existentes.';
$string['usermgmt_deptusers']         = 'Usuarios por departamento';
$string['usermgmt_deptusers_desc']    = 'Gestionar la asignación de usuarios por departamento.';
$string['usermgmt_uploadusers']       = 'Subir usuarios';
$string['usermgmt_uploadusers_desc']  = 'Carga masiva de usuarios desde un archivo CSV.';
$string['usermgmt_bulkdownload']      = 'Descarga masiva de usuarios';
$string['usermgmt_bulkdownload_desc'] = 'Descargar datos de usuarios de forma masiva.';
$string['usermgmt_userlist']          = 'Usuarios registrados';
$string['usermgmt_th_name']           = 'Nombre';
$string['usermgmt_th_email']          = 'Correo electrónico';
$string['usermgmt_th_lastaccess']     = 'Última conexión';
$string['usermgmt_th_actions']        = 'Acciones';
$string['usermgmt_edit']              = 'Editar';
$string['usermgmt_delete']            = 'Eliminar';
$string['usermgmt_delete_confirm']    = '¿Seguro que quieres eliminar este usuario? Esta acción no se puede deshacer.';
$string['usermgmt_deleted']           = 'Usuario eliminado correctamente.';
$string['usermgmt_never']             = 'Nunca';
$string['usermgmt_nousers']           = 'No se encontraron usuarios registrados en tu empresa.';

// Other management page — category labels (sub-options come from IOMAD lang files).
$string['othermgmt_title']              = 'Otras gestiones';
$string['othermgmt_heading']            = 'Otras gestiones';
$string['othermgmt_companies']          = 'Empresas';
$string['othermgmt_courses']            = 'Cursos';
$string['othermgmt_licenses']           = 'Licencias';
$string['othermgmt_competences']        = 'Competencias';
$string['othermgmt_reports']            = 'Informes';

// Company student limits (admin page).
$string['companylimits_heading']       = 'Límites de estudiantes por empresa';
$string['companylimits_button']        = 'Gestionar límites de empresa';
$string['companylimits_button_desc']   = 'Establece el número máximo de estudiantes que puede registrar cada empresa.';
$string['companylimits_title']         = 'Límites de estudiantes por empresa';
$string['companylimits_th_company']    = 'Empresa';
$string['companylimits_th_shortname']  = 'Nombre corto';
$string['companylimits_th_students']   = 'Estudiantes actuales';
$string['companylimits_th_maxlimit']   = 'Máx. estudiantes';
$string['companylimits_th_status']     = 'Estado';
$string['companylimits_unlimited']     = 'Ilimitado';
$string['companylimits_ok']            = 'OK';
$string['companylimits_full']          = 'Completo';
$string['companylimits_save']          = 'Guardar límites';
$string['companylimits_saved']         = 'Límites de estudiantes guardados correctamente.';
$string['companylimits_help']          = 'Pon 0 para estudiantes ilimitados.';
$string['companylimits_field_label']   = 'Máximo de usuarios activos';

// Upload users page.
$string['uploadusers_title']       = 'Subir usuarios';
$string['uploadusers_subtitle']    = 'Importar usuarios desde un archivo CSV a tu empresa.';
$string['uploadusers_file']        = 'Seleccionar archivo CSV';
$string['uploadusers_file_help']   = 'Formato .csv — separado por comas, codificación UTF-8';
$string['uploadusers_type']        = 'Tipo de carga';
$string['uploadusers_submit']      = 'Subir';
$string['uploadusers_cancel']      = 'Cancelar';
$string['uploadusers_nofile']      = 'No se ha subido ningún archivo. Selecciona un archivo CSV.';
$string['uploadusers_empty']       = 'El archivo CSV está vacío o no se pudo leer.';

// User management — limit display.
$string['usermgmt_limit_reached']      = 'Se ha alcanzado el número máximo de estudiantes de tu empresa. No puedes crear nuevos usuarios hasta que se aumente el límite.';
$string['usermgmt_upload_exceeds']     = 'El archivo CSV contiene {$a->csvcount} usuarios, pero tu empresa solo tiene {$a->remaining} plazas disponibles (límite: {$a->limit}). No se importaron usuarios. Reduce el archivo o solicita un límite mayor.';

// Course pricing.
$string['pricing_header']         = 'Precios';
$string['pricing_amount']         = 'Precio';
$string['pricing_amount_help']    = 'Establece el precio del curso. Usa 0 para cursos gratuitos.';
$string['pricing_currency']       = 'Moneda';
$string['pricing_error_negative'] = 'El precio no puede ser negativo.';

// Course comments.
$string['comments_title'] = 'Comentarios';
$string['comments_newest'] = 'Más recientes';
$string['comments_oldest'] = 'Más antiguos';
$string['comments_empty'] = 'Aún no hay comentarios. ¡Sé el primero en compartir tu opinión!';
$string['comments_load_more'] = 'Cargar más comentarios';
$string['comments_post'] = 'Publicar comentario';
$string['comments_post_reply'] = 'Publicar respuesta';
$string['comments_write'] = 'Escribe un comentario...';
$string['comments_write_reply'] = 'Escribe una respuesta...';
$string['comments_edit'] = 'Editar';
$string['comments_delete'] = 'Eliminar';
$string['comments_delete_confirm'] = '¿Seguro que quieres eliminar este comentario? Esta acción no se puede deshacer.';
$string['comments_edited'] = 'editado';
$string['comments_reply'] = 'Responder';
$string['comments_replies'] = 'Respuestas';
$string['comments_search_users'] = 'Buscar usuarios...';
$string['comments_no_users'] = 'No se encontraron usuarios';
$string['comments_just_now'] = 'Ahora mismo';
$string['comments_minutes_ago'] = 'min';
$string['comments_hours_ago'] = 'h';
$string['comments_days_ago'] = 'd';
$string['comments_slide'] = 'Diapositiva';
$string['comments_question'] = 'Pregunta';
$string['comments_chapter'] = 'Capítulo';
$string['comments_page'] = 'Página';
$string['comments_position'] = 'Posición';

// Statistics page.
$string['stats_title']              = 'Estadísticas';
$string['stats_heading']            = 'Estadísticas';
$string['stats_active_5days']       = 'Conectados (últimos 5 días)';
$string['stats_courses_started']    = 'Cursos iniciados';
$string['stats_courses_completed']  = 'Cursos completados';
$string['stats_completion_rate']    = 'Tasa de completado';
$string['stats_courses_available']  = 'Cursos disponibles';
$string['stats_weekly_completions'] = 'Cursos completados por semana';
$string['stats_weekly_active']      = 'Usuarios únicos conectados por semana';

// Capabilities.
$string['sm_graphics_plugin:view'] = 'View SM Graphic Layer';
$string['sm_graphics_plugin:post_comments'] = 'Post course comments';
$string['sm_graphics_plugin:delete_any_comment'] = 'Delete any course comment';

// Course page (Udemy-style player).
$string['course_page_back'] = 'Volver a cursos';
$string['course_page_learning_route'] = 'Ruta de aprendizaje';
$string['course_page_activities_count'] = 'Actividades';
$string['course_page_sections'] = 'Secciones';
$string['course_page_teachers'] = 'Profesores';
$string['course_page_students'] = 'Alumnos';
$string['course_page_description'] = 'Descripción';
$string['course_page_grades'] = 'Calificaciones';
$string['course_page_fullscreen'] = 'Pantalla completa';
$string['course_page_exit_fullscreen'] = 'Salir de pantalla completa';
$string['course_page_select_activity'] = 'Selecciona una actividad para empezar';
$string['course_page_prev'] = 'Anterior';
$string['course_page_next'] = 'Siguiente';
$string['course_page_complete'] = 'Completar';
$string['course_page_collapse_sidebar'] = 'Ocultar panel lateral';
$string['course_page_expand_sidebar'] = 'Mostrar panel lateral';
$string['course_page_grade_item'] = 'Actividad';
$string['course_page_grade_total'] = 'Total del curso';
$string['course_page_no_grades'] = 'Aún no hay calificaciones disponibles.';

// Course page counter.
$string['course_page_counter_slide'] = 'Diapositiva';
$string['course_page_counter_page'] = 'Página';
$string['course_page_counter_chapter'] = 'Capítulo';
$string['course_page_counter_question'] = 'Pregunta';
$string['course_page_counter_video'] = 'Vídeo';
$string['course_page_video_unsupported'] = 'Tu navegador no soporta la etiqueta de vídeo.';

// Focus mode.
$string['focus_mode'] = 'Modo concentración';

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
$string['gradescerts_nav']            = 'Notas y Diplomas';
$string['gradescerts_title']          = 'Notas y Diplomas';
$string['gradescerts_heading']        = 'Notas y Diplomas';
$string['gradescerts_desc']           = 'Diplomas obtenidos al superar los cursos. Descargables y verificables.';
$string['gradescerts_course']         = 'Curso';
$string['gradescerts_grade']          = 'Calificación';
$string['gradescerts_progress']       = 'Progreso';
$string['gradescerts_certificate']    = 'Diploma';
$string['gradescerts_download']       = 'Descargar Diploma';
$string['gradescerts_download_all']   = 'Descargar Todos';
$string['gradescerts_no_grade']       = 'Sin calificación';
$string['gradescerts_not_available']  = 'Aún no disponible';
$string['gradescerts_language']       = 'Idioma del diploma';
$string['gradescerts_hours']          = 'horas';
$string['gradescerts_no_courses']     = 'Sin cursos matriculados';
$string['gradescerts_inprogress']     = 'En progreso';
$string['gradescerts_official']       = 'Certificado oficial';
$string['gradescerts_issued']         = 'Expedido el';
$string['gradescerts_download_pdf']   = 'Descargar PDF';

// Certificate verification.
$string['verify_title']         = 'Verificación de certificado';
$string['verify_heading']       = 'Verificar un certificado';
$string['verify_placeholder']   = 'Introduce el código de verificación';
$string['verify_button']        = 'Verificar';
$string['verify_student']       = 'Alumno';
$string['verify_course']        = 'Curso';
$string['verify_date']          = 'Fecha de finalización';
$string['verify_company']       = 'Empresa';
$string['verify_code']          = 'Código de verificación';
$string['verify_success']       = 'Certificado verificado correctamente';
$string['verify_notfound']      = 'No se encontró ningún certificado con ese código de verificación.';
$string['verify_back_login']    = 'Volver al inicio de sesión';

// IOMAD dashboard (SmartMind card view).
$string['iomaddashboard_heading']  = 'Administración';
$string['iomad_configuration']     = 'Configuración';
$string['iomad_users']             = 'Usuarios';
$string['iomad_emailtemplates']    = 'Plantillas de correo';
$string['iomad_shop']              = 'Tienda';

// Course management page.
$string['nav_coursemanagement']   = 'Gestión de cursos';
$string['coursemgmt_heading']    = 'Gestión de cursos';
$string['coursemgmt_create']     = 'Crear curso';
$string['coursemgmt_create_desc'] = 'Crear un nuevo curso';
$string['coursemgmt_assign']     = 'Asignar a empresa';
$string['coursemgmt_assign_desc'] = 'Asignar cursos a tu empresa';
$string['coursemgmt_restore']      = 'Restaurar curso';
$string['coursemgmt_restore_desc'] = 'Restaurar un curso desde copia de seguridad';
$string['coursemgmt_createcat']      = 'Crear categoría';
$string['coursemgmt_createcat_desc'] = 'Crear una nueva categoría de cursos';

// Create category page.
$string['createcat_title']      = 'Crear categoría';
$string['createcat_name']       = 'Nombre de la categoría';
$string['createcat_image']      = 'Imagen de fondo';
$string['createcat_image_help'] = 'JPG, PNG o WebP. Tamaño recomendado: 600×300 px.';
$string['createcat_sortorder']  = 'Orden';
$string['createcat_preview']    = 'Vista previa de la tarjeta';
$string['createcat_submit']     = 'Crear categoría';
$string['createcat_cancel']     = 'Cancelar';
$string['createcat_success']    = 'Categoría creada correctamente.';

// Manage categories page.
$string['managecat_title']          = 'Gestionar categorías';
$string['managecat_save']           = 'Guardar cambios';
$string['managecat_updated']        = 'Categoría actualizada correctamente.';
$string['managecat_deleted']        = 'Categoría eliminada correctamente.';
$string['managecat_delete_confirm'] = '¿Seguro que quieres eliminar esta categoría? Los cursos asignados serán desvinculados.';
$string['managecat_empty']          = 'No se encontraron categorías.';
$string['coursemgmt_managecat']      = 'Gestionar categorías';
$string['coursemgmt_managecat_desc'] = 'Ver y organizar categorías de cursos';
$string['coursemgmt_sharepoint']      = 'Import from SharePoint';
$string['coursemgmt_sharepoint_desc'] = 'Import a full course from SharePoint';
$string['coursemgmt_companies']    = 'Empresas';
$string['coursemgmt_courses_col']  = 'Cursos asignados';
$string['coursemgmt_users_col']    = 'Usuarios';

// AI Configuration.
$string['ai_settings_heading']      = 'AI Configuration';
$string['gemini_api_key']           = 'Gemini API Key';
$string['gemini_api_key_desc']      = 'Google Generative AI API key for activity duration estimation. Get one at https://ai.google.dev/';
$string['gemini_model']             = 'Gemini Model';
$string['gemini_model_desc']        = 'AI model name for duration estimation (default: gemma-3-4b-it).';
$string['ai_suggested_duration']    = 'AI-suggested: {$a} hours — you can change this value';
$string['ai_duration_label']        = 'AI-estimated';

// Course player redesign.
$string['course_page_module_content']       = 'Contenido del módulo';
$string['course_page_mycourses_breadcrumb'] = 'Mis cursos';

// My Courses page.
$string['page_eyebrow']              = 'APRENDIZAJE ILIMITADO';
$string['mycourses_title']           = 'Mis cursos';
$string['mycourses_desc']            = 'Gestiona tu formación y continúa donde lo dejaste.';
$string['mycourses_inprogress']      = 'En progreso';
$string['mycourses_completed']       = 'Completados';
$string['mycourses_all']             = 'Todos';
$string['mycourses_continue']        = 'Continuar';
$string['mycourses_review']          = 'Revisar';
$string['mycourses_completed_label'] = 'completado';
$string['mycourses_resource']        = 'Recurso';
$string['mycourses_of']              = 'de';
$string['mycourses_empty']           = 'No tienes cursos matriculados todavía.';
$string['mycourses_nav']             = 'Mis cursos';
$string['catalogue_modules']         = 'módulos';

// Profile page.
$string['profile_xp_total']      = 'XP Total';
$string['profile_streak']        = 'Racha actual';
$string['profile_contents']      = 'Contenidos';
$string['profile_hours']         = 'Horas';
$string['profile_streak_keep']   = 'Racha actual · ¡Sigue así!';
$string['profile_days_to']       = '{$a->days} días para +{$a->xp} XP';
$string['profile_weekly']        = 'ACTIVIDAD SEMANAL';
$string['profile_level']         = 'Nivel {$a}';
$string['profile_xp_remaining']  = '{$a->xp} XP para nivel {$a->level}';
$string['profile_since']         = 'Desde {$a}';

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
