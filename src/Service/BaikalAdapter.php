<?php

declare(strict_types = 1);

namespace Eziat\BaikalWrapperBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Mysqli\MysqliConnection;
use Doctrine\DBAL\Driver\Mysqli\MysqliException;
use Eziat\BaikalWrapperBundle\Utils\VCardUtil;

/**
 * @author Tomas Jakl <tomasjakll@gmail.com>
 */
class BaikalAdapter
{
    /** @var $vCardUtil VCardUtil */
    private $vCardUtil;

    /** @var $baikalDbalConnection Connection */
    private $baikalDbalConnection;

    /**
     * Initialize the DB connection to Baikal.
     *
     * @throws MysqliException
     */
    public function __construct(VCardUtil $vCardUtil, string $dbHost, string $dbName, string $dbUser, string $dbPassword)
    {
        $this->vCardUtil            = $vCardUtil;
        $params                     = [
            "dbname" => $dbName,
            "host"   => $dbHost,
        ];
        $this->baikalDbalConnection = new MysqliConnection($params, $dbUser, $dbPassword);
    }

    /**
     * Adds or updates a card in the Baikal DB.
     * Returns true, if a change in Baikal was necessary
     *
     * @param string $cardUri     The cardUri in Baikal to look for
     * @param string $personVcard The VCard content as string
     * @param int    $addressBookId
     *
     * @return bool true, if the card was inserted or changed, false if the data in baikal is already up-to-date
     */
    public function addOrUpdateVcard(string $cardUri, string $personVcard, int $addressBookId) : bool
    {
        $existingCard = $this->getBaikalVcardFromCardUri($cardUri);

        if ($existingCard) {
            $existingVcardString = $existingCard["carddata"];
            $vcardNeedsUpdate    = !$this->vCardUtil->vcardsAreEqual($existingVcardString, $personVcard);

            if ($vcardNeedsUpdate) {
                $this->updateVcard($cardUri, $personVcard);

                return true;
            } else {
                return false;
            }
        } else {
            $this->insertNewVcard($addressBookId, $cardUri, $personVcard);

            return true;
        }
    }

    public function baikalUserExists(string $username) : bool
    {
        $principalUri    = $this->getBaikalPrincipalUriByUsername($username);
        $foundPrincipals = $this->baikalDbalConnection
            ->query("SELECT id FROM principals WHERE uri = '$principalUri'")
            ->fetchAll();

        return sizeof($foundPrincipals) > 0;
    }

    public function createBaikalUser(string $username, string $passwordDigest, ?string $email = null) : void
    {
        $this->baikalDbalConnection
            ->query(
                "INSERT INTO users (username, digesta1) ".
                "VALUES ('$username', '$passwordDigest')");

        $principalUri = $this->getBaikalPrincipalUriByUsername($username);
        if ($email) {
            $this->baikalDbalConnection
                ->query(
                    "INSERT INTO principals (uri, displayname, email) ".
                    "VALUES ('$principalUri', '$username', '$email')");
        } else {
            $this->baikalDbalConnection
                ->query(
                    "INSERT INTO principals (uri, displayname) ".
                    "VALUES ('$principalUri', '$username')");
        }
    }

    public function deleteBaikalUser(string $username) : void
    {
        $principalUri = $this->getBaikalPrincipalUriByUsername($username);
        $this->baikalDbalConnection
            ->query(
                "DELETE cards FROM cards INNER JOIN addressbooks ON cards.addressbookid = addressbooks.id
                WHERE principaluri = '$principalUri'");
        $this->baikalDbalConnection
            ->query("DELETE FROM addressbooks WHERE principaluri = '$principalUri'");
        $this->baikalDbalConnection
            ->query("DELETE FROM principals WHERE uri = '$principalUri'");
        $this->baikalDbalConnection
            ->query("DELETE FROM users WHERE username = '$username'");
    }

    public function createAddressBookFor(string $username, ?string $addressBookName = "default") : void
    {
        $principalUri = $this->getBaikalPrincipalUriByUsername($username);
        $this->baikalDbalConnection
            ->query(
                "INSERT INTO addressbooks (principaluri, displayname, uri, description, synctoken)  ".
                "VALUES ('$principalUri', '$addressBookName', '$addressBookName', '$addressBookName', 0)");
    }

    /**
     * Gets the default address book of the user (principal) with the given
     * principal URI
     */
    public function getAddressBookIdByUsername(string $username, string $addressBookName = "default") : ?int
    {
        $principalUri    = $this->getBaikalPrincipalUriByUsername($username);
        $addressBookLine = $this->baikalDbalConnection
            ->query(
                "SELECT id FROM addressbooks ".
                "WHERE principaluri = '$principalUri'".
                "AND uri = '$addressBookName'")
            ->fetchAll();

        if (sizeof($addressBookLine)) {
            $addressBookId = $addressBookLine[0]["id"];

            return $addressBookId;
        } else {
            return null;
        }
    }

    public function markAddressbookUpdated(int $addressBookId) : void
    {
        $this->baikalDbalConnection->query("UPDATE addressbooks SET synctoken = synctoken + 1 WHERE id = $addressBookId");
    }

    protected function insertNewVcard(int $addressBookId, string $vCardUri, string $personVcardString) : void
    {
        $currentUnixDate = (new \DateTime("now"))->getTimestamp();

        $sql  = "INSERT INTO cards(addressbookid, carddata, uri, lastmodified)"
                ."VALUES (?, ?, ?, ?)";
        $stmt = $this->baikalDbalConnection->prepare($sql);
        $stmt->bindValue(1, $addressBookId);
        $stmt->bindValue(2, $personVcardString);
        $stmt->bindValue(3, $vCardUri);
        $stmt->bindValue(4, $currentUnixDate);
        $stmt->execute();
    }

    protected function updateVcard(string $vCardUri, string $personVcardString) : void
    {
        $currentUnixDate = (new \DateTime("now"))->getTimestamp();
        $sql             = "UPDATE cards ".
                           "SET carddata = ?, ".
                           "lastmodified=? ".
                           "WHERE uri = ?";
        $stmt            = $this->baikalDbalConnection->prepare($sql);
        $stmt->bindValue(1, $personVcardString);
        $stmt->bindValue(2, $currentUnixDate);
        $stmt->bindValue(3, $vCardUri);
        $stmt->execute();
    }

    /**
     * Finds an existing vcard for the cardUri in the Baikal DB
     * Returns null if no card is found or an assoc. array representing
     * the data DB entry
     */
    protected function getBaikalVcardFromCardUri(string $vCardUri) : ?array
    {
        $existingCards = $this->baikalDbalConnection
            ->query("SELECT * FROM cards WHERE uri = '$vCardUri'")->fetchAll();
        $existingCard  = array_pop($existingCards);

        return $existingCard;
    }

    public function getBaikalPrincipalUriByUsername(string $username) : string
    {
        return "principals/$username";

    }
}