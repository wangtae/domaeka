<?php

/**
 * Tiny MCE 6
 */
if (!defined('_GNUBOARD_')) {
  exit;
}
function editor_html($id, $content, $is_dhtml_editor = true)
{
  global $g5, $config, $w, $board, $write;
  static $js = true;
  $hostname = bp_get_hostname();

  if (
    $is_dhtml_editor && $content && (
      (!$w && (isset($board['bo_insert_content']) && !empty($board['bo_insert_content'])))
      || ($w == 'u' && isset($write['wr_option']) && strpos($write['wr_option'], 'html') === false))
  ) {       //글쓰기 기본 내용 처리
    if (preg_match('/\r|\n/', $content) && $content === strip_tags($content, '<a><strong><b>')) {  //textarea로 작성되고, html 내용이 없다면
      $content = nl2br($content);
    }
  }

  $editor_url = G5_EDITOR_URL . '/' . $config['cf_editor'];
  $editor_path = G5_EDITOR_PATH . '/' . $config['cf_editor'];

  $html = '';
  $html .= '<span class="sr-only">웹에디터 시작</span>';

  if ($is_dhtml_editor && $js) {
    $html .= '<script src="' . BB_ASSETS_URL . '/js/@tinymce/tinymce-jquery/dist/tinymce-jquery.min.js"></script>';
    $html .= '<script src="' . G5_EDITOR_URL . '/' . $config['cf_editor'] . '/tinymce.min.js"></script>';
    //$html .= '<script src="' . G5_EDITOR_URL . '/' . $config['cf_editor'] . '/langs/ko_KR.js"></script>';
    $js = false;
  }

  $tinymce_class = $is_dhtml_editor ? 'tinymce-editor ' : '';
  $html .= '<textarea id="' . $id . '" name="' . $id . '" class=" form-control ' . $tinymce_class . '" maxlength="65536">' . $content . '</textarea>';
  $html .= '<span class="sr-only">웹 에디터 끝</span>';

  $html .= "<script>
  $(function(){
  const useDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const isSmallScreen = window.matchMedia('(max-width: 1023.5px)').matches;
  const bb_image_upload = (blobInfo, progress) => new Promise((resolve, reject) => {
  const xhr = new XMLHttpRequest();
  xhr.withCredentials = false;
  xhr.open('POST', '{$editor_url}/image-uploader.php');
  
    xhr.upload.onprogress = (e) => {
      progress(e.loaded / e.total * 100);
    };
  
    xhr.onload = () => {
      if (xhr.status === 403) {
        reject({ message: 'HTTP Error: ' + xhr.status, remove: true });
        return;
      }
  
      if (xhr.status < 200 || xhr.status >= 300) {
        reject('HTTP Error: ' + xhr.status);
        return;
      }
  
      const json = JSON.parse(xhr.responseText);
  
      if (!json || typeof json.location != 'string') {
        reject('Invalid JSON: ' + xhr.responseText);
        return;
      }
  
      resolve(json.location);
    };
  
    xhr.onerror = () => {
      reject('Image upload failed due to a XHR Transport error. Code: ' + xhr.status);
    };
  
    const formData = new FormData();
    formData.append('file', blobInfo.blob(), blobInfo.filename());
  
    xhr.send(formData);
  });

  tinymce.init({selector: '#{$id}',
  language:'ko_KR',
  height: 600,
  skin: 'oxide',
  content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:16px }',
  image_caption: true,
  quickbars_selection_toolbar: 'bold italic | quicklink h2 h3 blockquote quickimage quicktable',
  noneditable_class: 'mceNonEditable',
  contextmenu: 'link image table',
  importcss_append: true,
  images_file_types : 'jpg,png,webp,gif',
  plugins: 'preview importcss searchreplace autolink directionality code visualblocks visualchars fullscreen image link media template codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help charmap quickbars emoticons',
  editimage_cors_hosts: ['picsum.photos'],
  menubar: 'file edit view insert format tools table help',
  toolbar: 'undo redo | bold italic underline strikethrough table | fontfamily fontsize blocks | alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist | forecolor backcolor removeformat | pagebreak | charmap emoticons | fullscreen  preview save print | image media template link anchor codesample | ltr rtl',
  toolbar_sticky: false,
  toolbar_sticky_offset: isSmallScreen ? 102 : 108,
  image_advtab: true,
  toolbar_mode: 'sliding',
  contextmenu: 'link image table',
  table_default_attributes: {
    class: 'table table-bordered'
  },
  mobile: {
    menubar: 'false',
    plugins: 'lists autolink image',
    toolbar: 'undo redo bold italic image | fontfamily fontsize | alignleft aligncenter alignright alignjustify'
  },
  templates: '" . G5_PLUGIN_URL . "/editor/{$config['cf_editor']}/bp_templates.php',
  
  content_css: [
      '" . BB_ASSETS_URL . "/css/bootstrap-icons/font/bootstrap-icons.css',
      '" . BB_ASSETS_URL . "/css/bootstrap.css',
      '" . BB_ASSETS_URL . "/css/askseo.css'
  ],
  relative_urls : false,
  remove_script_host : false,
  convert_urls : true,
  paste_data_images: true,
  images_upload_url: '{$editor_url}/image-uploader.php',
  automatic_uploads: true,
  images_reuse_filename:false,
  images_upload_base_path : '{$editor_path}/images',
  images_upload_credentials:true,
  file_picker_types: 'file image media',
  block_unsupported_drop : true,
  images_upload_handler: bb_image_upload,
  image_dimensions: false,
  image_class_list: [{title: 'Responsive', value: 'img-fluid'}]
  
});
});
</script>";

  return $html;
}


// textarea 로 값을 넘긴다. javascript 반드시 필요
function get_editor_js($id, $is_dhtml_editor = true)
{
  if ($is_dhtml_editor) {
    return " var {$id}_editor_data = tinymce.get('{$id}').getContent(); ";
  } else {
    return ' var ' . $id . '_editor = document.getElementById("' . $id . '"); ';
  }
}


//  textarea 의 값이 비어 있는지 검사
function chk_editor_js($id, $is_dhtml_editor = true)
{
  if ($is_dhtml_editor) {
    return ' if (!' . $id . '_editor_data) { alert("내용을 입력해 주십시오."); tinymce.activeEditor.focus();  return false; } if (typeof(f.' . $id . ')!="undefined") f.' . $id . '.value = ' . $id . '_editor_data; ';
  } else {
    return ' if (!' . $id . '_editor.value) { alert("내용을 입력해 주십시오."); ' . $id . '_editor.focus(); return false; } ';
  }
}
