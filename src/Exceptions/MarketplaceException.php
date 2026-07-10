<?php

namespace PelicanMarketplace\PluginMarketplace\Exceptions;

use Exception;

/**
 * Base class for every exception this plugin throws, so calling code
 * can catch a single type when it only cares "did the marketplace
 * layer fail" without caring about the specific reason.
 */
class MarketplaceException extends Exception {}
