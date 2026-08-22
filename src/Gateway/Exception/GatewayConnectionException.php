<?php

declare(strict_types=1);

namespace DropContext\Gateway\Exception;

/** The gateway host could not be reached at all (DNS, refused connection, timeout). */
class GatewayConnectionException extends GatewayException {}
