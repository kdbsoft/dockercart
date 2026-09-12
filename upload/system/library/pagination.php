<?php
/**
 * @package		OpenCart
 * @author		Daniel Kerr
 * @copyright	Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license		https://opensource.org/licenses/GPL-3.0
 * @link		https://www.opencart.com
*/

/**
* Pagination class
*/
class Pagination {
	public $total = 0;
	public $page = 1;
	public $limit = 20;
	public $num_links = 5;
	public $url = '';
	public $text_first = '|&lt;';
	public $text_last = '&gt;|';
	public $text_next = '&gt;';
	public $text_prev = '&lt;';

	/**
     * 
     *
     * @return	text
     */
	public function render() {
		$total = $this->total;

		if ($this->page < 1) {
			$page = 1;
		} else {
			$page = $this->page;
		}

		if (!(int)$this->limit) {
			$limit = 10;
		} else {
			$limit = $this->limit;
		}

		$num_links = $this->num_links;
		$num_pages = ceil($total / $limit);

		$this->url = str_replace('%7Bpage%7D', '{page}', $this->url);

		$output = '<ul class="pagination">';

		if ($page > 1) {
			$output .= '<li><a href="' . str_replace(array('&amp;page={page}', '?page={page}', '&page={page}'), '', $this->url) . '">' . $this->text_first . '</a></li>';
			
			if ($page - 1 === 1) {
				$output .= '<li><a href="' . str_replace(array('&amp;page={page}', '?page={page}', '&page={page}'), '', $this->url) . '">' . $this->text_prev . '</a></li>';
			} else {
				$output .= '<li><a href="' . str_replace('{page}', $page - 1, $this->url) . '">' . $this->text_prev . '</a></li>';
			}
		}

		if ($num_pages > 1) {
			if ($num_pages <= $num_links) {
				$start = 1;
				$end = $num_pages;
			} else {
				$start = $page - floor($num_links / 2);
				$end = $page + floor($num_links / 2);

				if ($start < 1) {
					$end += abs($start) + 1;
					$start = 1;
				}

				if ($end > $num_pages) {
					$start -= ($end - $num_pages);
					$end = $num_pages;
				}
			}

			$pages = range($start, $end);

			if ($start > 1) {
				array_unshift($pages, 1);
			}

			if ($end < $num_pages) {
				$pages[] = $num_pages;
			}

			// Fill single-page gaps so an ellipsis never hides just one page.
			$expanded = [];
			$previous = 0;

			foreach ($pages as $page_num) {
				if ($previous && $page_num - $previous == 2) {
					$expanded[] = $previous + 1;
				}

				$expanded[] = $page_num;
				$previous = $page_num;
			}

			$previous = 0;

			foreach ($expanded as $page_num) {
				if ($previous && $page_num - $previous > 1) {
					$output .= '<li class="disabled"><span>&hellip;</span></li>';
				}

				if ($page == $page_num) {
					$output .= '<li class="active"><span>' . $page_num . '</span></li>';
				} elseif ($page_num === 1) {
					$output .= '<li><a href="' . str_replace(array('&amp;page={page}', '?page={page}', '&page={page}'), '', $this->url) . '">' . $page_num . '</a></li>';
				} else {
					$output .= '<li><a href="' . str_replace('{page}', (string)$page_num, $this->url) . '">' . $page_num . '</a></li>';
				}

				$previous = $page_num;
			}
		}

		if ($page < $num_pages) {
			$output .= '<li><a href="' . str_replace('{page}', $page + 1, $this->url) . '">' . $this->text_next . '</a></li>';
			$output .= '<li><a href="' . str_replace('{page}', $num_pages, $this->url) . '">' . $this->text_last . '</a></li>';
		}

		$output .= '</ul>';

		if ($num_pages > 1) {
			return $output;
		} else {
			return '';
		}
	}

	/**
	 * Build the "Showing X to Y of Z" summary string.
	 * Returns an empty string when there are no records at all.
	 *
	 * @param	string	$text	sprintf format, e.g. "Showing %d to %d of %d (%d Pages)"
	 *
	 * @return	string
	 */
	public function renderResults($text) {
		$total = (int)$this->total;

		if ($total == 0) {
			return '';
		}

		if ($this->page < 1) {
			$page = 1;
		} else {
			$page = $this->page;
		}

		if (!(int)$this->limit) {
			$limit = 10;
		} else {
			$limit = $this->limit;
		}

		$start = ($page - 1) * $limit + 1;
		$end = ($start - 1 > $total - $limit) ? $total : (($page - 1) * $limit + $limit);

		return sprintf($text, $start, $end, $total, ceil($total / $limit));
	}
}
