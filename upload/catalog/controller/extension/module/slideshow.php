<?php
class ControllerExtensionModuleSlideshow extends Controller {
	public function index($setting) {
		static $module = 0;		

		$this->load->model('design/banner');
		$this->load->model('tool/image');



		$data['banners'] = array();

		$results = $this->model_design_banner->getBanner($setting['banner_id']);

		foreach ($results as $result) {
			// Support external image URLs or local images. Use original image URL (no resize).
			if (filter_var($result['image'], FILTER_VALIDATE_URL)) {
				$image_landscape = $result['image'];
			} elseif (is_file(DIR_IMAGE . $result['image'])) {
				// Serve original image file via web path (no resizing/compression)
				$image_landscape = HTTP_SERVER . 'image/' . ltrim($result['image'], '/');
			} else {
				$image_landscape = '';
			}

			// Process portrait image
			if (!empty($result['image_portrait'])) {
				if (filter_var($result['image_portrait'], FILTER_VALIDATE_URL)) {
					$image_portrait = $result['image_portrait'];
				} elseif (is_file(DIR_IMAGE . $result['image_portrait'])) {
					$image_portrait = HTTP_SERVER . 'image/' . ltrim($result['image_portrait'], '/');
				} else {
					$image_portrait = '';
				}
			} else {
				$image_portrait = '';
			}

			// Accent/title processing (allow [word] to be highlighted)
			$accent_color = !empty($result['accent_color']) ? $result['accent_color'] : '';
			// Prepare a translucent background variant (hex alpha) for badge use (50% alpha)
			$accent_bg = '';
			$badge_text_color = $accent_color ? $this->badgeTextColor($accent_color) : '#ffffff';
			if ($accent_color) {
				$hex = ltrim($accent_color, '#');
				// expand 3-digit hex to 6-digit
				if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
					$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
				}
				if (preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
					$r = hexdec(substr($hex, 0, 2));
					$g = hexdec(substr($hex, 2, 2));
					$b = hexdec(substr($hex, 4, 2));
					$accent_bg = 'rgba(' . $r . ',' . $g . ',' . $b . ',0.5)';
				}
			}
			// Decode any HTML entities saved in DB to avoid double-encoding
			$raw_title = html_entity_decode((string)$result['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

			if ($accent_color) {
				$title_html = preg_replace(
					'/\[(.+?)\]/',
					'<span style="color:' . htmlspecialchars($accent_color, ENT_QUOTES) . '">$1</span>',
					htmlspecialchars($raw_title, ENT_QUOTES, 'UTF-8')
				);
			} else {
				$title_html = htmlspecialchars($raw_title, ENT_QUOTES, 'UTF-8');
				$title_html = preg_replace('/\[(.+?)\]/', '$1', $title_html);
			}

			// Video processing (YouTube takes priority over MP4)
			$video_type = isset($result['video_type']) ? $result['video_type'] : '';
			$video = isset($result['video']) ? $result['video'] : '';
			$video_url = '';

			if ($video_type === 'youtube' && $video) {
				$video_url = 'https://www.youtube.com/embed/' . urlencode($video) . '?autoplay=1&mute=1&loop=1&playlist=' . urlencode($video) . '&controls=0&showinfo=0&rel=0&enablejsapi=1';
			} elseif ($video_type === 'mp4' && $video) {
				if (filter_var($video, FILTER_VALIDATE_URL)) {
					$video_url = $video;
				} elseif (is_file(DIR_IMAGE . $video)) {
					$video_url = HTTP_SERVER . 'image/' . ltrim($video, '/');
				}
			}

			$data['banners'][] = array(
				'title'              => html_entity_decode((string)$result['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				'title_html'         => $title_html,
				'subtitle'           => isset($result['subtitle']) ? html_entity_decode((string)$result['subtitle'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
				'badge'              => isset($result['badge']) ? html_entity_decode((string)$result['badge'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : (isset($result['accent_text']) ? html_entity_decode((string)$result['accent_text'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : ''),
				'primary_btn_text'   => isset($result['primary_btn_text']) ? $result['primary_btn_text'] : '',
				'primary_btn_link'   => isset($result['primary_btn_link']) ? $result['primary_btn_link'] : '',
				'primary_btn_text_color' => isset($result['primary_btn_text_color']) ? $result['primary_btn_text_color'] : '',
				'primary_btn_bg_color'   => isset($result['primary_btn_bg_color']) ? $result['primary_btn_bg_color'] : '',
				'secondary_btn_text' => isset($result['secondary_btn_text']) ? $result['secondary_btn_text'] : '',
				'secondary_btn_link' => isset($result['secondary_btn_link']) ? $result['secondary_btn_link'] : '',
				'accent_color'       => $accent_color,
				'accent_bg'          => $accent_bg,
				'badge_text_color'   => $badge_text_color,
				'link'               => $result['link'],
				'image'              => $image_landscape,
				'image_portrait'     => $image_portrait,
				'video_type'         => $video_type,
				'video_url'          => $video_url
			);
		}

		$data['module'] = $module++;

		return $this->load->view('extension/module/slideshow', $data);
	}

	private function badgeTextColor($hex) {
		$hex = ltrim($hex, '#');
		if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return '#ffffff';
		$r = hexdec(substr($hex, 0, 2));
		$g = hexdec(substr($hex, 2, 2));
		$b = hexdec(substr($hex, 4, 2));
		return ((0.299 * $r + 0.587 * $g + 0.114 * $b) / 255) > 0.5 ? '#000000' : '#ffffff';
	}
}