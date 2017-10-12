<?php

namespace Application\Controller\Console;

use Onesky\Api\Client;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Json\Json;

class OneskyappController extends AbstractActionController
{
    /**
     * @var array
     */
    private $languages;

    /**
     * @var array
     */
    private $options;

    public function __construct(
        array $languages,
        array $options
    ) {
        $this->languages = $languages;
        $this->options = $options;
    }

    public function downloadAction()
    {
        $client = new Client();

        $client->setApiKey($this->options['api_key'])->setSecret($this->options['api_secret']);

        $fronendDir = realpath(__DIR__ . '/../../../../../../assets/languages');

        foreach ($this->languages as $language) {
            $response = $client->translations('export', [
                'project_id'       => $this->options['project_id'],
                'locale'           => $language,
                'source_file_name' => 'frontend.json'
            ]);

            $filepath = $fronendDir . '/' . $language . '.json';

            print $filepath . "\n";

            file_put_contents($filepath, $response);
        }

        $backendDir = realpath(__DIR__ . '/../../../../language');

        foreach ($this->languages as $language) {
            $response = $client->translations('export', [
                'project_id'       => $this->options['project_id'],
                'locale'           => $language,
                'source_file_name' => 'backend.json'
            ]);

            $data = Json::decode($response, Json::TYPE_ARRAY);

            $content = '<?php return ' . var_export($data, true) . ';';

            $filepath = $backendDir. '/' . $language . '.php';

            print $filepath . "\n";

            file_put_contents($filepath, $content);
        }

        return "done\n";
    }
}
