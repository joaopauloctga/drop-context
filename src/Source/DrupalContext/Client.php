<?php

namespace DrupalContext\Source\DrupalContext;

use GuzzleHttp\Client as ClientHttp;

class Client {

  protected ClientHttp $client;

  public function __construct() {
    $this->client = new ClientHttp([
      'base_uri' => 'https://drupal-context.ddev.site',
      'headers' => [
        'Accept' => 'application/json',
      ],
      'timeout' => 60,
      'verify' => FALSE,
    ]);
  }

  public function get(string $path, array $options = []) {
    return $this->client->get("/api" . $path, $options);
  }

}