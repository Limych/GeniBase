<?php
namespace App\Rs;

/**
 *
 * @author Limych
 *
 */
class Rel extends \Gedcomx\Rs\Client\Rel
{

    /**************************
     * GeniBase specific rels *
     **************************/
        /**
         * A link that points to contributor agent resource.
         */
    const CONTRIBUTOR = 'contributor';
}
