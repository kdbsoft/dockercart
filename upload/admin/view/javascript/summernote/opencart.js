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
			height: 300,
			branding: false,
			promotion: false,
			menubar: false,
			plugins: 'advlist autolink lists link image table code fullscreen searchreplace visualblocks',
			toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image table | code fullscreen',
			convert_urls: false,
			relative_urls: false,
			remove_script_host: false,
			setup: function(editor) {
				editor.on('change keyup Undo Redo SetContent', function() {
					editor.save();
				});
			}
		});

		element.setAttribute('data-tinymce-initialized', '1');
		return true;
	}

	// Override summernotes image manager
	$('[data-toggle=\'summernote\']').each(function() {
		var element = this;

		if (initTinyMceForElement(element)) {
			return;
		}

		if ($(this).attr('data-lang') && $(this).attr('data-lang')!='en-gb') {
			$('head').append('<script type="text/javascript" src="view/javascript/summernote/lang/summernote-' + $(this).attr('data-lang') + '.min.js"></script>');
		}

		$(element).summernote({
			lang: $(this).attr('data-lang'),
			disableDragAndDrop: true,
			height: 300,
			emptyPara: '',
			codemirror: { // codemirror options
				mode: 'text/html',
				htmlMode: true,
				lineNumbers: true,
				theme: 'monokai'
			},
			fontSizes: ['8', '9', '10', '11', '12', '13', '14', '16', '18', '20', '24', '30', '36', '48' , '64'],
			toolbar: [
				['style', ['style']],
				['font', ['bold', 'underline', 'italic', 'clear']],
				['fontname', ['fontname']],
				['fontsize', ['fontsize']],
				['color', ['color']],
				['para', ['ul', 'ol', 'paragraph']],
				['table', ['table']],
				['insert', ['link', 'image', 'video']],
				['view', ['fullscreen', 'codeview', 'help']]
			],
			popover: {
				image: [
					['custom', ['imageAttributes']],
					['resize', ['resizeFull', 'resizeHalf', 'resizeQuarter', 'resizeNone']],
					['float', ['floatLeft', 'floatRight', 'floatNone']],
					['remove', ['removeMedia']]
				],
				link: [['link', ['linkDialogShow', 'unlink']]],
				table: [
					['add', ['addRowDown', 'addRowUp', 'addColLeft', 'addColRight']],
					['delete', ['deleteRow', 'deleteCol', 'deleteTable']]
				],
			},
			buttons: {
    			image: function() {
					var ui = $.summernote.ui;

					// create button
					var button = ui.button({
						contents: '<i class="note-icon-picture" />',
						tooltip: $.summernote.lang[$.summernote.options.lang].image.image,
						click: function () {
							$('#modal-image').remove();

							$.ajax({
								url: 'index.php?route=common/filemanager&user_token=' + getURLVar('user_token'),
								dataType: 'html',
								beforeSend: function() {
									$('#button-image i').replaceWith('<i class="fa fa-circle-o-notch fa-spin"></i>');
									$('#button-image').prop('disabled', true);
								},
								complete: function() {
									$('#button-image i').replaceWith('<i class="fa fa-upload"></i>');
									$('#button-image').prop('disabled', false);
								},
								success: function(html) {
									$('body').append('<div id="modal-image" class="modal">' + html + '</div>');

									$('#modal-image').modal('show');

									$('#modal-image').delegate('a.thumbnail', 'click', function(e) {
										e.preventDefault();

										$(element).summernote('insertImage', $(this).attr('href'));

										$('#modal-image').modal('hide');
									});
								}
							});
						}
					});

					return button.render();
				}
  			}
		});
	});

	$(document).on('submit', 'form', function() {
		if (typeof tinymce !== 'undefined') {
			tinymce.triggerSave();
		}

		$('[data-toggle=\'summernote\']').each(function() {
			if ($(this).summernote('codeview.isActivated')) {
				$(this).summernote('codeview.deactivate'); 
			}
		});
	});
});
