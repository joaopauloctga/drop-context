<?php

declare(strict_types=1);

namespace DrupalContext\Gateway\Exception;

/** A 400 from entity_gateway — bad filter/sort/pagination query string. */
class GatewayBadRequestException extends GatewayException {}
