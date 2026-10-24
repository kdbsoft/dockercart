$(document).ready(function() {
	function initTinyMceForElement(element) {
		if (typeof tinymce === 'undefined') {
			return false;
		}

		if (!element.id) {
			element.id = 'tinymce-' + Math.random().toString(36).slice(2, 10);
		}

		if (element.getAttribute('data-tinymce-initialized') === '1') {
			return true;
		}

		tinymce.init({
			selector: '#' + element.id,
			license_key: 'gpl',
			height: 375,
			branding: false,
			promotion: false,
			menubar: false,
			content_style: [
				"@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');",
				"body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 13px; color: #333; }",
				"body :not(code):not(pre):not(kbd):not(samp):not(tt) { font-family: inherit !important; }",
				"body code, body pre, body kbd, body samp, body tt { font-family: Monaco, Menlo, 'Ubuntu Mono', monospace; }"
			].join('\n'),
			invalid_styles: 'font-family font-size',
			plugins: 'advlist autolink lists link image media table code fullscreen searchreplace visualblocks',
			toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image media table | code fullscreen',
			sandbox_iframes: false,
			convert_urls: false,
			relative_urls: false,
			remove_script_host: false,
			file_picker_types: 'image media',
			file_picker_callback: function(callback, value, meta) {
				$('#modal-image').remove();

				$.ajax({
					url: 'index.php?route=common/filemanager&user_token=' + getURLVar('user_token'),
					dataType: 'html',
					beforeSend: function() {
						$('#button-image i, #button-image svg').addClass('dc-spin');
						$('#button-image').prop('disabled', true);
					},
					complete: function() {
						$('#button-image i, #button-image svg').removeClass('dc-spin');
						$('#button-image').prop('disabled', false);
					},
					success: function(html) {
						$('body').append('<div id="modal-image" class="modal" style="z-index: 1500;">' + html + '</div>');

						$('#modal-image').modal('show');

						$('#modal-image').delegate('a.thumbnail', 'click', function(e) {
							e.preventDefault();

							callback($(this).attr('href'), {alt: ''});

							$('#modal-image').modal('hide');
						});
					}
				});
			},
			setup: function(editor) {
				editor.on('change keyup Undo Redo SetContent', function() {
					editor.save();
				});
			}
		});

		element.setAttribute('data-tinymce-initialized', '1');
		return true;
	}

	$('[data-toggle=\'tinymce\']').each(function() {
		initTinyMceForElement(this);
	});

	$(document).on('submit', 'form', function() {
		if (typeof tinymce !== 'undefined') {
			tinymce.triggerSave();
		}
	});
});
