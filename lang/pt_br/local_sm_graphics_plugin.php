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
 * SM Graphic Layer Plugin - Brazilian Portuguese language strings.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin identity.
$string['pluginname']       = 'SM Graphic Layer';
$string['privacy:metadata'] = 'O plugin SM Graphic Layer não armazena dados pessoais.';

// Admin settings — master toggle.
$string['enabled']          = 'Ativar SM Graphic Layer';
$string['enabled_desc']     = 'Ativa ou desativa a camada visual em todo o site. Se desativado, o Moodle é exibido com sua aparência normal.';

// Admin settings — colors section.
$string['colors_heading']       = 'Cores da marca';
$string['color_primary']        = 'Cor principal';
$string['color_primary_desc']   = 'Cor da marca usada em botões, links e destaques. Formato hexadecimal, ex. #6366f1';
$string['color_header_bg']      = 'Cor de fundo do cabeçalho';
$string['color_header_bg_desc'] = 'Cor de fundo da barra de navegação superior. Formato hexadecimal, ex. #1a1f35';
$string['color_sidebar_bg']     = 'Cor de fundo da barra lateral';
$string['color_sidebar_bg_desc']= 'Cor de fundo do painel de navegação lateral. Formato hexadecimal, ex. #ffffff';

// Admin settings — logo section.
$string['logo_heading']     = 'Logotipo';
$string['logo_url']         = 'URL do logotipo';
$string['logo_url_desc']    = 'URL completa da imagem do logotipo exibida no cabeçalho. Deixe em branco para usar o logotipo do site Moodle.';

// Admin settings — plugin updates.
$string['update_heading']           = 'Atualizações do plugin';
$string['update_button']            = 'Verificar atualizações';
$string['update_button_desc']       = 'Verifica no GitHub se há versões mais recentes do plugin e tema.';
$string['update_current_version']   = 'Versão atual';
$string['update_new_version']       = 'Nova versão';
$string['update_available']         = 'Atualização disponível';
$string['update_available_msg']     = 'Atualização disponível: {$a->current} &rarr; {$a->new}';
$string['update_uptodate']          = 'O plugin está atualizado (v{$a}).';
$string['update_confirm']           = 'Isto irá baixar e instalar a versão mais recente do plugin e do tema SmartMind do GitHub. Uma atualização do Moodle será executada em seguida.';
$string['update_confirm_question']  = 'Deseja continuar com a atualização?';
$string['update_success']           = 'Atualização concluída com sucesso. Clique em Continuar para executar a atualização do Moodle.';
$string['update_failed']            = 'A atualização falhou. Tente novamente ou atualize manualmente.';
$string['update_downloading']       = 'Baixando atualização...';
$string['update_downloaded']        = 'Baixado';
$string['update_installing']        = 'Instalando atualização...';
$string['update_copying']           = 'Copiando arquivos...';
$string['update_files_copied']      = 'arquivos copiados';
$string['update_caches_purged']     = 'Caches limpos';
$string['update_plugin_theme']      = 'Atualizar Plugin + Tema';
$string['update_page_title']        = 'Atualizar SM Graphic Layer';
$string['update_fetch_error']       = 'Não foi possível obter informações de atualização do GitHub.';
$string['update_not_writable']      = 'O diretório não tem permissão de escrita';
$string['update_copy_failed']       = 'Erro ao copiar arquivos';
$string['update_download_failed']   = 'Erro no download';
$string['update_extract_failed']    = 'Erro ao extrair o arquivo ZIP';
$string['update_step_plugin']       = 'Passo 1: Atualizando plugin';
$string['update_step_theme']        = 'Passo 2: Atualizando tema';

// Welcome page.
$string['welcome_title']      = 'Bem-vindo';
$string['welcome_heading']    = 'Bem-vindo ao SmartMind';

// Catalogue categories.
$string['catalogue_category'] = 'Categoria do catálogo';

// Navigation label overrides.
$string['nav_home']       = 'Catálogo';
$string['nav_dashboard']  = 'Espaço pessoal';
$string['nav_mycourses']  = 'Adicionar curso';

// Course pricing.
$string['pricing_header']         = 'Preço';
$string['pricing_amount']         = 'Preço';
$string['pricing_amount_help']    = 'Defina o preço do curso. Use 0 para cursos gratuitos.';
$string['pricing_currency']       = 'Moeda';
$string['pricing_error_negative'] = 'O preço não pode ser negativo.';

// Comentários do curso.
$string['comments_title'] = 'Comentários';
$string['comments_newest'] = 'Mais recentes';
$string['comments_oldest'] = 'Mais antigos';
$string['comments_empty'] = 'Ainda não há comentários. Seja o primeiro a compartilhar suas ideias!';
$string['comments_load_more'] = 'Carregar mais comentários';
$string['comments_post'] = 'Publicar comentário';
$string['comments_post_reply'] = 'Publicar resposta';
$string['comments_write'] = 'Escreva um comentário...';
$string['comments_write_reply'] = 'Escreva uma resposta...';
$string['comments_edit'] = 'Editar';
$string['comments_delete'] = 'Excluir';
$string['comments_delete_confirm'] = 'Tem certeza de que deseja excluir este comentário? Esta ação não pode ser desfeita.';
$string['comments_edited'] = 'editado';
$string['comments_reply'] = 'Responder';
$string['comments_replies'] = 'Respostas';
$string['comments_search_users'] = 'Buscar usuários...';
$string['comments_no_users'] = 'Nenhum usuário encontrado';
$string['comments_just_now'] = 'Agora mesmo';
$string['comments_minutes_ago'] = 'min atrás';
$string['comments_hours_ago'] = 'horas atrás';
$string['comments_days_ago'] = 'dias atrás';
$string['comments_slide'] = 'Slide';
$string['comments_question'] = 'Questão';
$string['comments_chapter'] = 'Capítulo';
$string['comments_page'] = 'Página';
$string['comments_position'] = 'Posição';

// Email de credenciais para novos usuários.
$string['messageprovider:newusercredentials'] = 'Credenciais de acesso para novos usuários';
$string['newuser_email_subject'] = 'Suas credenciais de acesso — {$a->sitename}';
$string['newuser_email_small']   = 'Suas credenciais de acesso para {$a->sitename} foram criadas.';
$string['newuser_email_body']    = 'Olá {$a->firstname},

Sua conta foi criada em {$a->sitename} ({$a->company}).

Suas credenciais de acesso:
  Usuário: {$a->username}
  Senha:   {$a->password}

Acesse aqui: {$a->loginurl}

Você será solicitado a alterar sua senha no primeiro acesso.

Atenciosamente,
Equipe {$a->sitename}';
$string['newuser_email_body_html'] = '<p>Olá <strong>{$a->firstname}</strong>,</p>
<p>Sua conta foi criada em <strong>{$a->sitename}</strong> ({$a->company}).</p>
<table style="border-collapse:collapse;margin:16px 0;width:100%;max-width:400px;">
<tr><td style="padding:10px 16px;background:#f1f5f9;font-weight:600;border-radius:8px 0 0 0;">Usuário</td>
    <td style="padding:10px 16px;background:#f8fafc;border-radius:0 8px 0 0;">{$a->username}</td></tr>
<tr><td style="padding:10px 16px;background:#f1f5f9;font-weight:600;border-radius:0 0 0 8px;">Senha</td>
    <td style="padding:10px 16px;background:#f8fafc;font-family:monospace;letter-spacing:0.05em;border-radius:0 0 8px 0;">{$a->password}</td></tr>
</table>
<p><a href="{$a->loginurl}" style="display:inline-block;padding:12px 28px;background:#10b981;color:#fff;text-decoration:none;border-radius:10px;font-weight:600;font-size:1rem;">Acessar a plataforma</a></p>
<p style="color:#6b7280;font-size:0.85em;">Você será solicitado a alterar sua senha no primeiro acesso.</p>
<p>Atenciosamente,<br>Equipe {$a->sitename}</p>';

// Capacidades.
$string['sm_graphics_plugin:view'] = 'Ver SM Graphic Layer';
$string['sm_graphics_plugin:post_comments'] = 'Publicar comentários do curso';
$string['sm_graphics_plugin:delete_any_comment'] = 'Excluir qualquer comentário do curso';

// Página do curso (player estilo Udemy).
$string['course_page_back'] = 'Voltar aos Cursos';
$string['course_page_learning_route'] = 'Rota de Aprendizagem';
$string['course_page_activities_count'] = 'Atividades';
$string['course_page_sections'] = 'Seções';
$string['course_page_teachers'] = 'Professores';
$string['course_page_students'] = 'Alunos';
$string['course_page_description'] = 'Descrição';
$string['course_page_grades'] = 'Notas';
$string['course_page_fullscreen'] = 'Tela cheia';
$string['course_page_exit_fullscreen'] = 'Sair da tela cheia';
$string['course_page_select_activity'] = 'Selecione uma atividade para começar';
$string['course_page_prev'] = 'Anterior';
$string['course_page_next'] = 'Próximo';
$string['course_page_complete'] = 'Concluir';
$string['course_page_collapse_sidebar'] = 'Recolher barra lateral';
$string['course_page_expand_sidebar'] = 'Expandir barra lateral';
$string['course_page_grade_item'] = 'Atividade';
$string['course_page_grade_total'] = 'Total do curso';
$string['course_page_no_grades'] = 'Ainda não há notas disponíveis.';

// Contador da página do curso.
$string['course_page_counter_slide'] = 'Slide';
$string['course_page_counter_page'] = 'Página';
$string['course_page_counter_chapter'] = 'Capítulo';
$string['course_page_counter_question'] = 'Questão';
$string['course_page_counter_video'] = 'Vídeo';
$string['course_page_video_unsupported'] = 'Seu navegador não suporta a tag de vídeo.';

// Modo foco.
$string['focus_mode'] = 'Modo foco';

// Página de apresentação do curso.
$string['landing_program_content'] = 'Conteúdo do programa';
$string['landing_course_info']     = 'Informações do curso';
$string['landing_duration']        = 'Duração oficial';
$string['landing_language']        = 'Idioma';
$string['landing_category']        = 'Categoria';
$string['landing_modules']         = 'Módulos';
$string['landing_sections']        = 'Seções';
$string['landing_enrol']           = 'Matricular-se';
$string['landing_view_course']     = 'Ver curso';
$string['course_hours']            = 'Horas do curso';
$string['course_hours_help']       = 'Duração em horas exibida na página de apresentação do curso.';
$string['sepe_code']               = 'Código SEPE';
$string['sepe_code_help']          = 'Código do SEPE (Servicio Público de Empleo Estatal).';
$string['course_info_header']      = 'Informações do curso SmartMind';
$string['course_description']      = 'Descrição do curso';
$string['course_category_field']   = 'Categoria do curso';
$string['course_category_field_help'] = 'Categoria de formação SmartMind para este curso.';
$string['course_category_none']    = '-- Selecionar --';
$string['smartmind_code']          = 'Código SmartMind';
$string['smartmind_code_help']     = 'Identificador de curso SmartMind.';
$string['course_level']            = 'Nível';
$string['course_level_help']       = 'Nível de dificuldade do curso.';
$string['level_beginner']          = 'Básico';
$string['level_medium']            = 'Intermediário';
$string['level_advanced']          = 'Avançado';
$string['completion_percentage']      = 'Percentual de conclusão';
$string['completion_percentage_help'] = 'Percentual do curso que deve ser concluído para ser considerado finalizado (0-100).';
$string['landing_level']              = 'Nível';
$string['landing_completion']         = 'Conclusão';
$string['landing_edit']               = 'Editar configurações do curso';
$string['landing_save']               = 'Salvar';
$string['landing_cancel']             = 'Cancelar';
$string['landing_add_activity']       = 'Adicionar Atividade';
$string['landing_edit_activity']      = 'Editar atividade';
$string['landing_delete_activity']    = 'Excluir atividade';
$string['landing_delete_confirm']     = 'Tem certeza de que deseja excluir esta atividade? Esta ação não pode ser desfeita.';
$string['landing_activity_type']      = 'Tipo de atividade';
$string['landing_activity_name']      = 'Nome da atividade';
$string['landing_activity_url']       = 'URL';
$string['landing_genially_url_hint']  = 'Cole a URL de incorporação do Genially (ex: https://view.genial.ly/...)';
$string['landing_add_redirect']       = 'Formulário padrão';
$string['landing_add_moodle']         = 'Outras Atividades';
$string['landing_video_upload']       = 'Enviar Arquivo';
$string['landing_video_upload_hint']  = 'Clique ou arraste um arquivo de vídeo aqui (mp4, webm, ogg, mov...)';
$string['landing_start']              = 'Iniciar Curso';
$string['landing_continue']           = 'Continuar Curso';
$string['landing_next_activity']      = 'Próxima atividade';
$string['landing_unenrol']            = 'Cancelar matrícula';
$string['landing_unenrol_confirm_title'] = 'Confirmar cancelamento';
$string['landing_unenrol_confirm']    = 'Tem certeza de que deseja cancelar sua matrícula neste curso? Seu progresso será perdido.';
$string['landing_enrolled_badge']     = 'Matriculado';
$string['landing_back']               = 'Voltar ao início';
$string['landing_continue_learning']  = 'Continuar aprendendo';
$string['landing_what_youll_learn']   = 'O que você vai aprender';
$string['landing_content_types']      = 'Tipos de conteúdo';
$string['landing_course_content']     = 'Conteúdo do curso';
$string['landing_elements']           = 'elementos';
$string['landing_completed_count']    = 'completados';
$string['landing_min']                = 'min';
$string['landing_completed_label']    = 'completado';
$string['landing_of']                 = 'de';
$string['landing_lessons']            = 'lições';
$string['landing_min_remaining']      = 'min restantes';
$string['landing_cert_included']      = 'Certificado incluído';
$string['objectives_header']          = 'Objetivos de aprendizagem';
$string['objectives_add']             = 'Adicionar objetivo';
$string['objectives_placeholder']     = 'Digite um objetivo de aprendizagem...';
$string['objectives_remove']          = 'Remover';
$string['objectives_drag']            = 'Arrastar para reordenar';
$string['objectives_error_max']       = 'Máximo 20 objetivos permitidos.';
$string['objectives_restore_hint']    = 'Um objetivo por linha';
$string['restore_desc_hint']          = 'Descrição do curso na página de apresentação. Será traduzida automaticamente.';
$string['restore_objectives_hint']    = 'Adicione objetivos de aprendizagem. Serão traduzidos automaticamente para outros idiomas.';
$string['restore_select_company']     = 'Selecione uma empresa';
$string['restore_select_all']         = 'Selecionar todas';
$string['restore_company']            = 'Empresa';
$string['restore_company_short']      = 'Nome curto';
$string['restore_new_course']         = 'Restaurar como novo curso';

// Página de Notas e Diplomas.
$string['gradescerts_nav']            = 'Notas e Diplomas';
$string['gradescerts_title']          = 'Notas e Diplomas';
$string['gradescerts_heading']        = 'Notas e Diplomas';
$string['gradescerts_course']         = 'Curso';
$string['gradescerts_grade']          = 'Nota';
$string['gradescerts_progress']       = 'Progresso';
$string['gradescerts_certificate']    = 'Diploma';
$string['gradescerts_download']       = 'Baixar Diploma';
$string['gradescerts_download_all']   = 'Baixar Todos';
$string['gradescerts_no_grade']       = 'Sem nota';
$string['gradescerts_not_available']  = 'Ainda não disponível';
$string['gradescerts_language']       = 'Idioma do diploma';
$string['gradescerts_hours']          = 'horas';
$string['gradescerts_no_courses']     = 'Sem cursos matriculados';

// Verificação de certificados.
$string['verify_title']         = 'Verificação de Certificado';
$string['verify_heading']       = 'Verificar um Certificado';
$string['verify_placeholder']   = 'Insira o código de verificação';
$string['verify_button']        = 'Verificar';
$string['verify_student']       = 'Estudante';
$string['verify_course']        = 'Curso';
$string['verify_date']          = 'Data de conclusão';
$string['verify_company']       = 'Empresa';
$string['verify_code']          = 'Código de verificação';
$string['verify_success']       = 'Certificado verificado com sucesso';
$string['verify_notfound']      = 'Nenhum certificado encontrado com esse código de verificação.';
$string['verify_back_login']    = 'Voltar ao login';

// Painel IOMAD (vista com cards SmartMind).
$string['iomaddashboard_heading']  = 'Administração';
$string['iomad_configuration']     = 'Configuração';
$string['iomad_users']             = 'Usuários';
$string['iomad_emailtemplates']    = 'Modelos de e-mail';
$string['iomad_shop']              = 'Loja';

// Página de gestão de cursos.
$string['nav_coursemanagement']   = 'Gestão de cursos';
$string['coursemgmt_heading']    = 'Gestão de cursos';
$string['coursemgmt_create']     = 'Criar curso';
$string['coursemgmt_create_desc'] = 'Criar um novo curso';
$string['coursemgmt_assign']     = 'Atribuir a empresa';
$string['coursemgmt_assign_desc'] = 'Atribuir cursos à sua empresa';
$string['coursemgmt_restore']      = 'Restaurar curso';
$string['coursemgmt_restore_desc'] = 'Restaurar um curso a partir de backup';
$string['coursemgmt_createcat']      = 'Criar categoria';
$string['coursemgmt_createcat_desc'] = 'Criar uma nova categoria de cursos';

// Página de criar categoria.
$string['createcat_title']      = 'Criar categoria';
$string['createcat_name']       = 'Nome da categoria';
$string['createcat_image']      = 'Imagem de fundo';
$string['createcat_image_help'] = 'JPG, PNG ou WebP. Tamanho recomendado: 600×300 px.';
$string['createcat_sortorder']  = 'Ordem';
$string['createcat_preview']    = 'Pré-visualização do cartão';
$string['createcat_submit']     = 'Criar categoria';
$string['createcat_cancel']     = 'Cancelar';
$string['createcat_success']    = 'Categoria criada com sucesso.';

// Página de gerir categorias.
$string['managecat_title']          = 'Gerir categorias';
$string['managecat_save']           = 'Guardar alterações';
$string['managecat_updated']        = 'Categoria atualizada com sucesso.';
$string['managecat_deleted']        = 'Categoria eliminada com sucesso.';
$string['managecat_delete_confirm'] = 'Tem a certeza de que pretende eliminar esta categoria? Os cursos atribuídos serão desvinculados.';
$string['managecat_empty']          = 'Nenhuma categoria encontrada.';
$string['coursemgmt_managecat']      = 'Gerir categorias';
$string['coursemgmt_managecat_desc'] = 'Ver e organizar as categorias de cursos';
$string['coursemgmt_companies']    = 'Empresas';
$string['coursemgmt_courses_col']  = 'Cursos atribuídos';
$string['coursemgmt_users_col']    = 'Usuários';

// Configuração de IA.
$string['ai_settings_heading']      = 'Configuração de IA';
$string['gemini_api_key']           = 'Chave API do Gemini';
$string['gemini_api_key_desc']      = 'Chave da API do Google Generative AI para estimativa de duração de atividades. Obtenha uma em https://ai.google.dev/';
$string['gemini_model']             = 'Modelo do Gemini';
$string['gemini_model_desc']        = 'Nome do modelo de IA para estimativa de duração (padrão: gemma-3-4b-it).';
$string['ai_suggested_duration']    = 'IA sugeriu: {$a} horas — você pode alterar este valor';
$string['ai_duration_label']        = 'Estimado por IA';

// Redesign do player de cursos.
$string['course_page_module_content']       = 'Conteúdo do módulo';
$string['course_page_mycourses_breadcrumb'] = 'Meus cursos';
