<?php

namespace PelicanMarketplace\PluginMarketplace\Exceptions;

/**
 * Thrown (and caught internally, never bubbled to the UI as a hard error)
 * whenever an upstream repository API cannot be reached or returns a
 * malformed response. Search/details services degrade gracefully by
 * catching this and surfacing it as a per-repository error banner
 * instead of failing the whole request.
 */
class RepositoryUnavailableException extends MarketplaceException {}
