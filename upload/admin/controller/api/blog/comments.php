<?php
/**
 * DockerCart admin REST API — blog comments (moderation).
 *
 *   GET    /v1/blog/comments              list (status/post/author/email filters)
 *   GET    /v1/blog/comments/{id}         single comment
 *   PATCH  /v1/blog/comments/{id}         edit author/email/text/rating/status
 *   POST   /v1/blog/comments/{id}/approve publish (status = 1)
 *   DELETE /v1/blog/comments/{id}         delete
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\Serializer;

class ControllerApiBlogComments extends \Dockercart\Api\Controller {
	public function index() {
		$this->load->model('extension/module/dockercart_blog_comment');

		$pagination = $this->pagination();

		$filters = [
			'filter_post'   => $this->queryString('post'),
			'filter_author' => $this->queryString('author'),
			'filter_email'  => $this->queryString('email'),
			'start'         => $pagination['start'],
			'limit'         => $pagination['limit'],
		];

		if ($this->queryString('status') !== '') {
			$filters['filter_status'] = $this->queryInt('status');
		}

		$rows = [];

		foreach ($this->model_extension_module_dockercart_blog_comment->getComments($filters) as $comment) {
			$rows[] = $this->commentResponse($comment);
		}

		$total = (int)$this->model_extension_module_dockercart_blog_comment->getTotalComments($filters);

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], $total));
	}

	public function get() {
		$this->respondResource($this->commentResponse($this->requireComment($this->param('comment_id'))));
	}

	public function patch() {
		$comment_id = $this->param('comment_id');

		$comment = $this->requireComment($comment_id);

		$this->assertPreconditions($this->commentResponse($comment));

		$data = $this->validateRequest($this->body(), [
			'author' => ['string', 'max:64'],
			'email'  => ['email', 'max:96'],
			'text'   => ['string', 'max:65535'],
			'rating' => ['int', 'min:0', 'max:5'],
			'status' => ['int', 'in:0,1'],
		]);

		if (!$data) {
			throw new HttpException(Error::VALIDATION_FAILED, ['body' => ['no updatable fields supplied']]);
		}

		$this->load->model('extension/module/dockercart_blog_comment');

		// author/text are rendered unescaped by the storefront blog (autoescape is
		// off) and by the panel list, so they are escaped exactly like the panel
		// form does it (Request::clean()). Fields that were not sent keep their
		// stored (already escaped) value.
		$this->model_extension_module_dockercart_blog_comment->editComment($comment_id, [
			'author' => isset($data['author']) ? Serializer::encodeTextWithin('author', $data['author'], 64) : (string)$comment['author'],
			'email'  => (string)($data['email'] ?? $comment['email']),
			'text'   => isset($data['text']) ? Serializer::encodeTextWithinBytes('text', $data['text'], 65535) : (string)$comment['text'],
			'rating' => (int)($data['rating'] ?? $comment['rating']),
			'status' => (int)($data['status'] ?? $comment['status']),
		]);

		$this->respondResource($this->commentResponse($this->requireComment($comment_id)));
	}

	public function approve() {
		$comment_id = $this->param('comment_id');

		$comment = $this->requireComment($comment_id);

		$this->assertPreconditions($this->commentResponse($comment));

		$this->load->model('extension/module/dockercart_blog_comment');

		$this->model_extension_module_dockercart_blog_comment->editComment($comment_id, [
			'author' => (string)$comment['author'],
			'email'  => (string)$comment['email'],
			'text'   => (string)$comment['text'],
			'rating' => (int)$comment['rating'],
			'status' => 1,
		]);

		$this->respondResource($this->commentResponse($this->requireComment($comment_id)));
	}

	public function delete() {
		$comment_id = $this->param('comment_id');

		$this->assertPreconditions($this->commentResponse($this->requireComment($comment_id)));

		$this->load->model('extension/module/dockercart_blog_comment');

		$this->model_extension_module_dockercart_blog_comment->deleteComment($comment_id);

		$this->respondNoContent();
	}

	private function requireComment(int $comment_id): array {
		$this->load->model('extension/module/dockercart_blog_comment');

		$comment = $this->model_extension_module_dockercart_blog_comment->getComment($comment_id);

		if (!$comment) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $comment;
	}

	private function commentResponse(array $comment): array {
		return [
			'comment_id'  => (int)$comment['comment_id'],
			'post_id'     => (int)$comment['post_id'],
			'post_name'   => isset($comment['post_name']) ? Serializer::decodeText($comment['post_name']) : '',
			'customer_id' => (int)$comment['customer_id'],
			'author'      => Serializer::decodeText($comment['author'] ?? ''),
			'email'       => (string)$comment['email'],
			'text'        => Serializer::decodeText($comment['text'] ?? ''),			'rating'      => (int)$comment['rating'],
			'status'      => (int)$comment['status'],
			'date_added'  => Serializer::datetime($comment['date_added']),
		];
	}
}
