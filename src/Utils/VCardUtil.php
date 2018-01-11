<?php

declare(strict_types = 1);

namespace Eziat\BaikalWrapperBundle\Utils;

/**
 * @author Tomas Jakl <tomasjakll@gmail.com>
 */
class VCardUtil
{
    public function vcardsAreEqual(string $vcard1, string $vcard2) : bool
    {
        return $this->stripCreationDateFromVcard($vcard1)
               == $this->stripCreationDateFromVcard($vcard2);
    }

    /**
     * Removes the creation date of the vcard. This is needed to compare 2 vcards that were created
     * at different times.
     *
     * @return string|string[]|null
     */
    private function stripCreationDateFromVcard(string $vcardString)
    {
        return preg_replace("/REV\:.*\n/", "", $vcardString);
    }
}