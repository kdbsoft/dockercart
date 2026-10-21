<?php
/**
 * DockerCart admin REST API — manufacturers.
 *
 *   GET    /v1/manufacturers        list (filter name/status, pagination)
 *   POST   /v1/manufacturers        create
 *   GET    /v1/manufacturers/{id}   single resource
 *   PUT    /v1/manufacturers/{id}   full replace
 *   PATCH  /v1/manufacturers/{id}   partial update
 *   DELETE /v1/manufacturers/{id}   delete
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\Payload\Manufacturer as ManufacturerPayload;

class ControllerApiCatalogManufacturers extends \Dockercart\Api\Controller {
	public function index() {
		$this->load->model('catalog/manufacturer');

		$pagination = $this->pagination();

		$filters = [
			'filter_name' => $this->queryString('name'),
			'sort'        => $this->queryString('sort', 'name'),
			'order'       => strtoupper($this->queryString('order', 'ASC')) === 'DESC' ? 'DESC' : 'ASC',
			'start'       => $pagination['start'],
			'limit'       => $pagination['limit'],
		];

		if ($this->queryString('status') !== '') {
			$filters['filter_status'] = $this->queryInt('status');
		}

		$payload = new ManufacturerPayload($this->registry);

		$rows = [];

		foreach ($this->model_catalog_manufacturer->getManufacturers($filters) as $manufacturer) {
			$rows[] = $payload->toResponse($manufacturer);
		}

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], (int)$this->model_catalog_manufacturer->getTotalManufacturers()));
	}

	public function get() {
		$this->respondResource($this->manufacturerResponse($this->param('manufacturer_id')));
	}

	public function add() {
		$this->load->model('catalog/manufacturer');

		$payload = new ManufacturerPayload($this->registry);

		$manufacturer_id = (int)$this->model_catalog_manufacturer->addManufacturer($payload->fromRequest($this->body()));

		$this->context()->etag = $this->etagFor($this->manufacturerResponse($manufacturer_id));

		$this->respondCreated(['manufacturer_id' => $manufacturer_id], '/api/v1/manufacturers/' . $manufacturer_id);
	}

	public function edit() {
		$manufacturer_id = $this->param('manufacturer_id');

		$this->assertPreconditions($this->manufacturerResponse($manufacturer_id));

		$payload = new ManufacturerPayload($this->registry);

		$this->model_catalog_manufacturer->editManufacturer($manufacturer_id, $payload->fromRequest($this->body()));

		$this->respondResource($this->manufacturerResponse($manufacturer_id));
	}

	public function patch() {
		$manufacturer_id = $this->param('manufacturer_id');

		$this->assertPreconditions($this->manufacturerResponse($manufacturer_id));

		$this->load->model('catalog/manufacturer');

		$payload = new ManufacturerPayload($this->registry);

		$patch = $payload->fromPatch($this->body());

		if (!$patch) {
			throw new HttpException(Error::VALIDATION_FAILED, ['body' => ['no updatable fields supplied']]);
		}

		$merged = array_merge($payload->fromExisting($manufacturer_id), $patch);

		$this->model_catalog_manufacturer->editManufacturer($manufacturer_id, $merged);

		$this->respondResource($this->manufacturerResponse($manufacturer_id));
	}

	public function delete() {
		$manufacturer_id = $this->param('manufacturer_id');

		$this->assertPreconditions($this->manufacturerResponse($manufacturer_id));

		$this->load->model('catalog/manufacturer');

		$this->model_catalog_manufacturer->deleteManufacturer($manufacturer_id);

		$this->respondNoContent();
	}

	private function requireManufacturer(int $manufacturer_id): array {
		$this->load->model('catalog/manufacturer');

		$manufacturer = $this->model_catalog_manufacturer->getManufacturer($manufacturer_id);

		if (!$manufacturer) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $manufacturer;
	}

	private function manufacturerResponse(int $manufacturer_id): array {
		$this->requireManufacturer($manufacturer_id);

		$payload = new ManufacturerPayload($this->registry);

		return $payload->toResponse($this->model_catalog_manufacturer->getManufacturer($manufacturer_id), [
			'descriptions' => $payload->descriptionsToResponse($this->model_catalog_manufacturer->getManufacturerDescriptions($manufacturer_id)),
			'stores'       => array_map('intval', $this->model_catalog_manufacturer->getManufacturerStores($manufacturer_id)),
			'seo_urls'     => $payload->seoUrlsToResponse($this->model_catalog_manufacturer->getManufacturerSeoUrls($manufacturer_id)),
		]);
	}
}
