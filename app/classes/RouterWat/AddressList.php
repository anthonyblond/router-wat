<?php
namespace RouterWat;

use PEAR2\Net\RouterOS;

/**
 * Represents an address list on the router. The values are stored in a json database file (intially
 * seeded by whatever is in address list on router) and used to update the router. The json file is
 * taken as the ground truth. That is, changes to the list on the router will have no affect if the
 * json file has already been created. If the file doesn't exist yet, or has been deleted, then
 * changes can be made directly on router.
 *
 * Calls to the update() method cause the existing list on router to be overwritten by the one is
 * json file. However, this is only down if a minimum amount of time has passed - if it has not,
 * then it simply won't be updated (i.e it isn't scheduled until later). However nothing is lost in
 * this case, since the file won't be overridden.
 */
class AddressList {
    private $config;
    private $listName;
    private $dbFilePath;

    function __construct($listName = null) {
        $config = json_decode(file_get_contents(CONFIG_FILE), true);
        $this->config = $config;

        if ($listName) {
            $this->listName = $listName;
        } else {
            $this->listName = $config['default_list_name'];
        }

        $this->dbFilePath = $config['data_dir'] . '/' . $this->listName . '.json';

        // If file doesn't exist, then start off with loading from router
        if (!file_exists($this->dbFilePath)) {
            $this->loadFromRouter();
        }
    }

    /**
     * @return array The array representation the list
     */
    public function getRaw() {
        $data = json_decode(file_get_contents($this->dbFilePath), true);
        return $data;
    }

    /**
     * @return array The array representation the addresses in list
     */
    public function getAddresses() {
        $data = json_decode(file_get_contents($this->dbFilePath), true);
        return $data['addresses'];
    }


    /**
     * @return integer the time in seconds until next update is allowed, or 0 if no wait is needed.
     */
    public function timeUntilUpdateAllowed() {
        $config = $this->config;
        $data = json_decode(file_get_contents($this->dbFilePath), true);
        $elapsed = time() - strtotime($data['last_update']);

        if ($elapsed > $data['min_wait_between_updates']) {
            return 0;
        } else {
            return $data['min_wait_between_updates'] - $elapsed;
        }
    }

    /**
     * Updates the list on router, but only if sufficient time has passed since last update, as
     * recorded in database file.
     *
     * @return  integer     0 if did update, otherwise an integer value indicating how many seconds
     *                      are left before next update can be done.
     *
     */
    public function update() {
        $timeUntilUpdateAllowed = $this->timeUntilUpdateAllowed();

        if ($timeUntilUpdateAllowed == 0) {
            $this->saveToRouter();
        }

        return $timeUntilUpdateAllowed;
    }

    /**
     * Adds address,comment pair to database file. Does not update router.
     * $replaceIfSameComment (default true) specifies whether to check for existing comments and
     * update the corresponding address if a match is found. This is used for when campaign offices
     * were stored in comment
     */
    public function add($address, $comment = null, $replaceIfSameComment = true) {
        $addresses = $this->getAddresses();

        $match = false;
        if ($replaceIfSameComment) {
            foreach ($addresses as &$address) {
                if ($address['comment'] == $comment) {
                    $address['address'] = $address;
                    $match = true;
                }
            }
        }
        unset($address);

        if (!$match) {
            $addresses[] = [
                'address' => $address,
                'comment' => $comment
            ];
        }

        $this->saveDbFile($addresses);
    }

    /**
     * Removes address from database file. Does not update router.
     */
    public function remove($address) {
        $addresses = $this->getAddresses();

        // Not implemented

        $this->saveDbFile($addresses);
    }

    /**
     * Overwrites list on router to match one in file
     */
    private function saveToRouter() {
        $config = $this->config;

        $data = json_decode(file_get_contents($this->dbFilePath), true);

        try {
            $client = new RouterOS\Client($config['router']['address'], $config['router']['username'], $config['router']['password']);
        } catch (\Exception $e) {
            throw new \Exception("Problem connecting to router. Probably wrong password.");
        }

        // Using Util class, see https://github.com/pear2/Net_RouterOS/wiki/Util-basics
        $util = new RouterOS\Util($client);
        $util->setMenu('/ip firewall address-list');

        // Remove existing list
        $util->remove(
            RouterOS\Query::where('list', $this->listName)
        );

        // Re-create list from data
        foreach ($data['addresses'] as $address) {
            $util->add([
                'list' => $data['list_name'],
                'address' => $address['address'],
                'comment' => $address['comment'],
            ]);
        }

        // Update the 'last_update' section of database file. Easiest for now to just re-write file
        $this->saveDbFile($data['addresses']);
    }

    /**
     * Updates database file to match records for list on router
     */
    private function loadFromRouter() {
        $config = $this->config;

        // GET CURRENT LIST FROM ROUTER
        try {
            $client = new RouterOS\Client($config['router']['address'], $config['router']['username'], $config['router']['password']);
        } catch (\Exception $e) {
            throw new \Exception("Problem connecting to router. Probably wrong password.");
        }

        $query = RouterOS\Query::where('list', $this->listName);
        $request = new RouterOS\Request('/ip/firewall/address-list/print', $query);

        $responses = $client->sendSync($request);

        $addresses = [];
        foreach ($responses as $response) {
            if ($response->getType() === RouterOS\Response::TYPE_DATA) {
                $addresses[] = [
                    'address' => $response->getProperty('address'),
                    'comment' => $response->getProperty('comment'),
                ];
            }
        }

        $this->saveDbFile($addresses);
    }

    private function saveDbFile($addresses) {
        // WRITE TO JSON FILE, OVERWRITING IF ALREADY EXISTS
        $data = [
            'list_name' => $this->listName,
            'last_update' => date('c'),
            'min_wait_between_updates' => $this->config['min_wait_between_updates'],
            'addresses' => $addresses,
        ];
        $dataJson = json_encode($data, JSON_PRETTY_PRINT);
        if (file_put_contents($this->dbFilePath, $dataJson) === FALSE) {
            throw new \Exception("Unable to write to dbFilePath");
        }
    }
}

?>