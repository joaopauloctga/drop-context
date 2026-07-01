<?php

namespace DrupalContext;

use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication {

  const VERSION = '1.0.0';

  const NAME = 'Drupal Context CLI';

  public function __construct() {
    parent::__construct(self::NAME, self::VERSION);
    $container = ContainerFactory::create();

    foreach ($container->findTaggedServiceIds('console.command') as $id => $_) {
      $this->add($container->get($id));
    }
  }

}
