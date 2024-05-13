<?php
namespace Snscripts\ITCReporter\Responses;

use Psr\Http\Message\ResponseInterface;
use Snscripts\ITCReporter\Interfaces\ResponseProcessor;

class SalesGetReport implements ResponseProcessor
{
    protected $Response;
    
    public function __construct(ResponseInterface $Response)
    {
        $this->Response = $Response;
    }

    public function process()
    {
        $contents = $this->Response->getBody()->getContents();
        if (empty($contents)) {
            return [];
        }

        $reportCSV = @gzdecode($contents);
        if (! isset($reportCSV) || ! $reportCSV) {
            return [];
        }

        $rows = explode("\n", $reportCSV);
        $headers = explode("\t", array_shift($rows));

        $reportArray = [];

        foreach ($rows as $values) {
            if (empty($values)) {
                continue;
            }

            $data = explode("\t", $values);

            $reportArray[] = array_combine(
                $headers,
                $data
            );
        }

        return $reportArray;
    }
}
