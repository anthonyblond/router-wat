<?php
namespace RouterWat;

use PEAR2\Net\RouterOS;

/**
 * Represents an address list on the router, maintaining a simple json database that it is
 * synchronised to when needed.
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
    public function getAll() {
        $data = json_decode(file_get_contents($this->dbFilePath), true);
        return $data;
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
        $config = $this->config;
        $data = json_decode(file_get_contents($this->dbFilePath), true);
        $elapsed = time() - strtotime($data['last_update']);

        if ($elapsed > $data['min_wait_between_updates']) {
            $this->saveToRouter();
            return 0;
        } else {
            return $data['min_wait_between_updates'] - $elapsed;
        }
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
        echo "Removing current entries for list '" . $this->listName . "' from router...";
        $util->remove(
            RouterOS\Query::where('list', $this->listName)
        );
        echo "Done<br/>";

        // Re-create list from data
        foreach ($data['addresses'] as $address) {
            echo "Adding $address[address]...";
            $util->add([
                'list' => $data['list_name'],
                'address' => $address['address'],
                'comment' => $address['comment'],
            ]);
            echo "Done<br/>";
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