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
 * SM Graphic Layer Plugin - Spanish language strings.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin identity.
$string['pluginname']       = 'SM Graphic Layer';
$string['privacy:metadata'] = 'El plugin SM Graphic Layer no almacena datos personales.';

// Admin settings — master toggle.
$string['enabled']          = 'Activar SM Graphic Layer';
$string['enabled_desc']     = 'Activa o desactiva la capa visual en todo el sitio. Si se desactiva, Moodle se muestra con su aspecto normal.';

// Admin settings — colors section.
$string['colors_heading']       = 'Colores de marca';
$string['color_primary']        = 'Color principal';
$string['color_primary_desc']   = 'Color de marca usado en botones, enlaces y acentos. Formato hexadecimal, p.ej. #6366f1';
$string['color_header_bg']      = 'Color de fondo del encabezado';
$string['color_header_bg_desc'] = 'Color de fondo de la barra de navegación superior. Formato hexadecimal, p.ej. #1a1f35';
$string['color_sidebar_bg']     = 'Color de fondo de la barra lateral';
$string['color_sidebar_bg_desc']= 'Color de fondo del panel de navegación lateral. Formato hexadecimal, p.ej. #ffffff';

// Admin settings — logo section.
$string['logo_heading']     = 'Logotipo';
$string['logo_url']         = 'URL del logotipo';
$string['logo_url_desc']    = 'URL completa de la imagen del logotipo que se muestra en el encabezado. Dejar en blanco para usar el logotipo del sitio Moodle.';

// Admin settings — plugin updates.
$string['update_heading']           = 'Actualizaciones del plugin';
$string['update_button']            = 'Buscar actualizaciones';
$string['update_button_desc']       = 'Comprueba en GitHub si hay versiones más recientes del plugin y tema.';
$string['update_current_version']   = 'Versión actual';
$string['update_new_version']       = 'Nueva versión';
$string['update_available']         = 'Actualización disponible';
$string['update_available_msg']     = 'Actualización disponible: {$a->current} &rarr; {$a->new}';
$string['update_uptodate']          = 'El plugin está actualizado (v{$a}).';
$string['update_confirm']           = 'Se descargará e instalará la última versión del plugin y el tema SmartMind desde GitHub. Después se ejecutará una actualización de Moodle.';
$string['update_confirm_question']  = '¿Desea continuar con la actualización?';
$string['update_success']           = 'Actualización completada correctamente. Haga clic en Continuar para ejecutar la actualización de Moodle.';
$string['update_failed']            = 'La actualización ha fallado. Inténtelo de nuevo o actualice manualmente.';
$string['update_downloading']       = 'Descargando actualización...';
$string['update_downloaded']        = 'Descargado';
$string['update_installing']        = 'Instalando actualización...';
$string['update_copying']           = 'Copiando archivos...';
$string['update_files_copied']      = 'archivos copiados';
$string['update_caches_purged']     = 'Cachés purgadas';
$string['update_plugin_theme']      = 'Actualizar Plugin + Tema';
$string['update_page_title']        = 'Actualizar SM Graphic Layer';
$string['update_fetch_error']       = 'No se pudo obtener información de actualización desde GitHub.';
$string['update_not_writable']      = 'El directorio no tiene permisos de escritura';
$string['update_copy_failed']       = 'Error al copiar archivos';
$string['update_download_failed']   = 'Error en la descarga';
$string['update_extract_failed']    = 'Error al extraer el archivo ZIP';
$string['update_step_plugin']       = 'Paso 1: Actualizando plugin';
$string['update_step_theme']        = 'Paso 2: Actualizando tema';

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
$string['usermgmt_delete_confirm']    = 'Seguro que quieres eliminar este usuario? Esta acción no se puede deshacer.';
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

// Límites de estudiantes por empresa (página admin).
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
$string['companylimits_help']          = 'Introduce 0 para estudiantes ilimitados.';
$string['companylimits_field_label']   = 'Máximo de usuarios activos';

// Página de subida de usuarios.
$string['uploadusers_title']       = 'Subir usuarios';
$string['uploadusers_subtitle']    = 'Importa usuarios desde un archivo CSV a tu empresa.';
$string['uploadusers_file']        = 'Seleccionar archivo CSV';
$string['uploadusers_file_help']   = 'Formato .csv — separado por comas, codificación UTF-8';
$string['uploadusers_type']        = 'Tipo de carga';
$string['uploadusers_submit']      = 'Subir';
$string['uploadusers_cancel']      = 'Cancelar';
$string['uploadusers_nofile']      = 'No se ha subido ningún archivo. Selecciona un archivo CSV.';
$string['uploadusers_empty']       = 'El archivo CSV está vacío o no se pudo leer.';

// Gestión de usuarios — visualización de límites.
$string['usermgmt_limit_reached']      = 'Se ha alcanzado el número máximo de estudiantes de tu empresa. No puedes crear nuevos usuarios hasta que se aumente el límite.';
$string['usermgmt_upload_exceeds']     = 'El archivo CSV contiene {$a->csvcount} usuarios, pero tu empresa solo tiene {$a->remaining} plazas disponibles (límite: {$a->limit}). No se ha importado ningún usuario. Reduce el archivo o solicita un límite mayor.';

// Course pricing.
$string['pricing_header']         = 'Precio';
$string['pricing_amount']         = 'Precio';
$string['pricing_amount_help']    = 'Establece el precio del curso. Usa 0 para cursos gratuitos.';
$string['pricing_currency']       = 'Moneda';
$string['pricing_error_negative'] = 'El precio no puede ser negativo.';

// Comentarios del curso.
$string['comments_title'] = 'Comentarios';
$string['comments_newest'] = 'Más recientes';
$string['comments_oldest'] = 'Más antiguos';
$string['comments_empty'] = 'Aún no hay comentarios. ¡Sé el primero en compartir tus ideas!';
$string['comments_load_more'] = 'Cargar más comentarios';
$string['comments_post'] = 'Publicar comentario';
$string['comments_post_reply'] = 'Publicar respuesta';
$string['comments_write'] = 'Escribe un comentario...';
$string['comments_write_reply'] = 'Escribe una respuesta...';
$string['comments_edit'] = 'Editar';
$string['comments_delete'] = 'Eliminar';
$string['comments_delete_confirm'] = '¿Estás seguro de que deseas eliminar este comentario? Esta acción no se puede deshacer.';
$string['comments_edited'] = 'editado';
$string['comments_reply'] = 'Responder';
$string['comments_replies'] = 'Respuestas';
$string['comments_search_users'] = 'Buscar usuarios...';
$string['comments_no_users'] = 'No se encontraron usuarios';
$string['comments_just_now'] = 'Ahora mismo';
$string['comments_minutes_ago'] = 'min atrás';
$string['comments_hours_ago'] = 'horas atrás';
$string['comments_days_ago'] = 'días atrás';
$string['comments_slide'] = 'Diapositiva';
$string['comments_question'] = 'Pregunta';
$string['comments_chapter'] = 'Capítulo';
$string['comments_page'] = 'Página';
$string['comments_position'] = 'Posición';

// Capacidades.
$string['sm_graphics_plugin:view'] = 'Ver SM Graphic Layer';
$string['sm_graphics_plugin:post_comments'] = 'Publicar comentarios del curso';
$string['sm_graphics_plugin:delete_any_comment'] = 'Eliminar cualquier comentario del curso';

// Página del curso (reproductor estilo Udemy).
$string['course_page_back'] = 'Volver a Cursos';
$string['course_page_learning_route'] = 'Ruta de Aprendizaje';
$string['course_page_activities_count'] = 'Actividades';
$string['course_page_sections'] = 'Secciones';
$string['course_page_teachers'] = 'Profesores';
$string['course_page_students'] = 'Estudiantes';
$string['course_page_description'] = 'Descripción';
$string['course_page_grades'] = 'Calificaciones';
$string['course_page_fullscreen'] = 'Pantalla completa';
$string['course_page_exit_fullscreen'] = 'Salir de pantalla completa';
$string['course_page_select_activity'] = 'Selecciona una actividad para comenzar';
$string['course_page_prev'] = 'Anterior';
$string['course_page_next'] = 'Siguiente';
$string['course_page_complete'] = 'Completar';
$string['course_page_collapse_sidebar'] = 'Contraer barra lateral';
$string['course_page_expand_sidebar'] = 'Expandir barra lateral';
$string['course_page_grade_item'] = 'Actividad';
$string['course_page_grade_total'] = 'Total del curso';
$string['course_page_no_grades'] = 'Aún no hay calificaciones disponibles.';

// Contador de página del curso.
$string['course_page_counter_slide'] = 'Diapositiva';
$string['course_page_counter_page'] = 'Página';
$string['course_page_counter_chapter'] = 'Capítulo';
$string['course_page_counter_question'] = 'Pregunta';
$string['course_page_counter_video'] = 'Video';
$string['course_page_video_unsupported'] = 'Su navegador no es compatible con la etiqueta de vídeo.';

// Modo enfoque.
$string['focus_mode'] = 'Modo enfoque';

// Página de presentación del curso.
$string['landing_program_content'] = 'Contenido del programa';
$string['landing_course_info']     = 'Información del curso';
$string['landing_duration']        = 'Duración oficial';
$string['landing_language']        = 'Idioma';
$string['landing_category']        = 'Categoría';
$string['landing_modules']         = 'Módulos';
$string['landing_sections']        = 'Secciones';
$string['landing_enrol']           = 'Matricularse';
$string['landing_view_course']     = 'Ver curso';
$string['course_hours']            = 'Horas del curso';
$string['course_hours_help']       = 'Duración en horas que se muestra en la página de presentación del curso.';
$string['sepe_code']               = 'Código SEPE';
$string['sepe_code_help']          = 'Código del SEPE (Servicio Público de Empleo Estatal).';
$string['course_info_header']      = 'Información del curso SmartMind';
$string['course_description']      = 'Descripción del curso';
$string['course_category_field']   = 'Categoría del curso';
$string['course_category_field_help'] = 'Categoría de formación SmartMind para este curso.';
$string['course_category_none']    = '-- Seleccionar --';
$string['smartmind_code']          = 'Código SmartMind';
$string['smartmind_code_help']     = 'Identificador de curso SmartMind.';
$string['course_level']            = 'Nivel';
$string['course_level_help']       = 'Nivel de dificultad del curso.';
$string['level_beginner']          = 'Básico';
$string['level_medium']            = 'Intermedio';
$string['level_advanced']          = 'Avanzado';
$string['completion_percentage']      = 'Porcentaje de finalización';
$string['completion_percentage_help'] = 'Porcentaje del curso que debe completarse para considerarlo terminado (0-100).';
$string['landing_level']              = 'Nivel';
$string['landing_completion']         = 'Finalización';
$string['landing_edit']               = 'Editar configuración del curso';
$string['landing_save']               = 'Guardar';
$string['landing_cancel']             = 'Cancelar';
$string['landing_add_activity']       = 'Añadir Actividad';
$string['landing_edit_activity']      = 'Editar actividad';
$string['landing_delete_activity']    = 'Eliminar actividad';
$string['landing_delete_confirm']     = '¿Estás seguro de que deseas eliminar esta actividad? Esta acción no se puede deshacer.';
$string['landing_activity_type']      = 'Tipo de actividad';
$string['landing_activity_name']      = 'Nombre de la actividad';
$string['landing_activity_url']       = 'URL';
$string['landing_genially_url_hint']  = 'Pega la URL de inserción de Genially (ej: https://view.genial.ly/...)';
$string['landing_add_redirect']       = 'Formulario estándar';
$string['landing_add_moodle']         = 'Otras Actividades';
$string['landing_video_upload']       = 'Subir Archivo';
$string['landing_video_upload_hint']  = 'Haz clic o arrastra un archivo de video aquí (mp4, webm, ogg, mov...)';
$string['landing_start']              = 'Iniciar Curso';
$string['landing_continue']           = 'Continuar Curso';
$string['landing_next_activity']      = 'Siguiente actividad';
$string['landing_unenrol']            = 'Darse de baja';
$string['landing_unenrol_confirm_title'] = 'Confirmar baja';
$string['landing_unenrol_confirm']    = '¿Estás seguro de que deseas darte de baja de este curso? Tu progreso se perderá.';
$string['landing_enrolled_badge']     = 'Matriculado';
$string['landing_back']               = 'Volver al inicio';
$string['landing_continue_learning']  = 'Continuar aprendiendo';
$string['landing_what_youll_learn']   = 'Qué aprenderás';
$string['landing_content_types']      = 'Tipos de contenido';
$string['landing_course_content']     = 'Contenido del curso';
$string['landing_elements']           = 'elementos';
$string['landing_completed_count']    = 'completados';
$string['landing_min']                = 'min';
$string['landing_completed_label']    = 'completado';
$string['landing_of']                 = 'de';
$string['landing_lessons']            = 'lecciones';
$string['landing_min_remaining']      = 'min restantes';
$string['landing_cert_included']      = 'Certificado incluido';
$string['objectives_header']          = 'Objetivos de aprendizaje';
$string['objectives_add']             = 'Añadir objetivo';
$string['objectives_placeholder']     = 'Escribe un objetivo de aprendizaje...';
$string['objectives_remove']          = 'Eliminar';
$string['objectives_drag']            = 'Arrastrar para reordenar';
$string['objectives_error_max']       = 'Máximo 20 objetivos permitidos.';
$string['objectives_restore_hint']    = 'Un objetivo por línea';
$string['restore_desc_hint']          = 'Descripción del curso en la página de presentación. Se traducirá automáticamente.';
$string['restore_objectives_hint']    = 'Añade objetivos de aprendizaje. Se traducirán automáticamente a otros idiomas.';
$string['restore_select_company']     = 'Seleccione una empresa';
$string['restore_select_all']         = 'Seleccionar todas';
$string['restore_company']            = 'Empresa';
$string['restore_company_short']      = 'Nombre corto';
$string['restore_new_course']         = 'Restaurar como curso nuevo';

// Página de Notas y Diplomas.
$string['gradescerts_nav']            = 'Notas y Diplomas';
$string['gradescerts_title']          = 'Notas y Diplomas';
$string['gradescerts_heading']        = 'Mis certificados';
$string['gradescerts_desc']           = 'Diplomas obtenidos al superar los cursos. Descargables y verificables.';
$string['gradescerts_official']       = 'Certificado oficial';
$string['gradescerts_issued']         = 'Expedido el';
$string['gradescerts_download_pdf']   = 'Descargar PDF';
$string['gradescerts_inprogress']     = 'En progreso';
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

// Verificación de certificados.
$string['verify_title']         = 'Verificación de Certificado';
$string['verify_heading']       = 'Verificar un Certificado';
$string['verify_placeholder']   = 'Introduce el código de verificación';
$string['verify_button']        = 'Verificar';
$string['verify_student']       = 'Estudiante';
$string['verify_course']        = 'Curso';
$string['verify_date']          = 'Fecha de finalización';
$string['verify_company']       = 'Empresa';
$string['verify_code']          = 'Código de verificación';
$string['verify_success']       = 'Certificado verificado correctamente';
$string['verify_notfound']      = 'No se encontró ningún certificado con ese código de verificación.';
$string['verify_back_login']    = 'Volver al inicio de sesión';

// Panel IOMAD (vista con tarjetas SmartMind).
$string['iomaddashboard_heading']  = 'Administración';
$string['iomad_configuration']     = 'Configuración';
$string['iomad_users']             = 'Usuarios';
$string['iomad_emailtemplates']    = 'Plantillas de correo';
$string['iomad_shop']              = 'Tienda';

// Página de gestión de cursos.
$string['nav_coursemanagement']   = 'Gestión de cursos';
$string['coursemgmt_heading']    = 'Gestión de cursos';
$string['coursemgmt_create']     = 'Crear curso';
$string['coursemgmt_create_desc'] = 'Crear un nuevo curso';
$string['coursemgmt_assign']     = 'Asignar a empresa';
$string['coursemgmt_assign_desc'] = 'Asignar cursos a tu empresa';
$string['coursemgmt_restore']      = 'Restaurar curso';
$string['coursemgmt_restore_desc'] = 'Restaurar un curso desde una copia de seguridad';
$string['coursemgmt_createcat']      = 'Crear categoría';
$string['coursemgmt_createcat_desc'] = 'Crear una nueva categoría de cursos';

// Página de crear categoría.
$string['createcat_title']      = 'Crear categoría';
$string['createcat_name']       = 'Nombre de la categoría';
$string['createcat_image']      = 'Imagen de fondo';
$string['createcat_image_help'] = 'JPG, PNG o WebP. Tamaño recomendado: 600×300 px.';
$string['createcat_sortorder']  = 'Orden';
$string['createcat_preview']    = 'Vista previa de la tarjeta';
$string['createcat_submit']     = 'Crear categoría';
$string['createcat_cancel']     = 'Cancelar';
$string['createcat_success']    = 'Categoría creada correctamente.';

// Página de gestionar categorías.
$string['managecat_title']          = 'Gestionar categorías';
$string['managecat_save']           = 'Guardar cambios';
$string['managecat_updated']        = 'Categoría actualizada correctamente.';
$string['managecat_deleted']        = 'Categoría eliminada correctamente.';
$string['managecat_delete_confirm'] = '¿Seguro que quieres eliminar esta categoría? Los cursos asignados serán desvinculados.';
$string['managecat_empty']          = 'No se encontraron categorías.';
$string['coursemgmt_managecat']      = 'Gestionar categorías';
$string['coursemgmt_managecat_desc'] = 'Ver y organizar las categorías de cursos';
$string['coursemgmt_companies']    = 'Empresas';
$string['coursemgmt_courses_col']  = 'Cursos asignados';
$string['coursemgmt_users_col']    = 'Usuarios';

// Rediseño del reproductor de cursos.
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
