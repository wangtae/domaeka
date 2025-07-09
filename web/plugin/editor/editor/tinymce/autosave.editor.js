function get_editor_wr_content() {
    return tinymce.activeEditor.getContent();
}

function put_editor_wr_content(content) {
    tinymce.activeEditor.setContent(content);

    return;
}