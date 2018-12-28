<?php namespace LaravelAds\Services\BingAds;

use SoapVar;
use SoapFault;
use Exception;
use ZipArchive;

use Microsoft\BingAds\V12\Reporting\ReportRequestStatusType;
use Microsoft\BingAds\V12\Reporting\PollGenerateReportRequest;

class ReportDownload
{
    /**
     * $service
     *
     */
    protected $serviceProxy = null;

    /**
     * $results
     *
     */
    protected $results = null;

    /**
     * __construct()
     *
     *
     */
    public function __construct($serviceProxy, $reportId)
    {
        $this->serviceProxy = $serviceProxy;

        $waitTime = 15 * 1;
        $reportRequestStatus = null;
        $reportName   = time();
        $DownloadPath = storage_path("app/".$reportName.'.zip');

        // This sample polls every 30 seconds up to 5 minutes.
        // In production you may poll the status every 1 to 2 minutes for up to one hour.
        // If the call succeeds, stop polling. If the call or
        // download fails, the call throws a fault.

        for ($i = 0; $i < 10; $i++)
        {
        	sleep($waitTime);

        	$reportRequestStatus = $this->pollGenerateReport($this->serviceProxy, $reportId)->ReportRequestStatus;

        	if ($reportRequestStatus->Status == ReportRequestStatusType::Success ||
        		$reportRequestStatus->Status == ReportRequestStatusType::Error)
        	{
        		break;
        	}
        }

        if ($reportRequestStatus != null)
        {
        	if ($reportRequestStatus->Status == ReportRequestStatusType::Success)
        	{
                $reportDownloadUrl = $reportRequestStatus->ReportDownloadUrl;

                if($reportDownloadUrl == null)
                {
                    print "No report data for the submitted request\n";
                }
                else
                {
                    $this->downloadFile($reportDownloadUrl, $DownloadPath);
                }

        	}
        	else if ($reportRequestStatus->Status == ReportRequestStatusType::Error)
        	{
        		printf("The request failed. Try requesting the report " .
        				"later.\nIf the request continues to fail, contact support.\n");
        	}
        	else
        	{
        		printf("The request is taking longer than expected.\n " .
        				"Save the report ID (%s) and try again later.\n",
        				$reportId);
        	}
        }

        $this->results = $this->extractZip($DownloadPath, $reportId);
    }


    protected function downloadFile($reportDownloadUrl, $downloadPath)
    {
        if (!$reader = fopen($reportDownloadUrl, 'rb'))
        {
            throw new Exception("Failed to open URL " . $reportDownloadUrl . ".");
        }

        if (!$writer = fopen($downloadPath, 'wb'))
        {
            fclose($reader);
            throw new Exception("Failed to create ZIP file " . $downloadPath . ".");
        }

        $bufferSize = 100 * 1024;
        while (!feof($reader))
        {
            if (false === ($buffer = fread($reader, $bufferSize)))
            {
                 fclose($reader);
                 fclose($writer);
                 throw new Exception("Read operation from URL failed.");
            }
            if (fwrite($writer, $buffer) === false)
            {
                 fclose($reader);
                 fclose($writer);
                 $exception = new Exception("Write operation to ZIP file failed.");
            }
        }
        fclose($reader);
        fflush($writer);
        fclose($writer);
    }

    protected function extractZip($location, $name)
    {
        $zip = new ZipArchive;
        if ($zip->open($location) === TRUE)
        {
            $zip->extractTo(storage_path('app/'));
            $zip->close();

            unlink($location);
        }

        $data = file(storage_path('app/').$name.'.csv');

        unlink(storage_path('app/').$name.'.csv');

        return $data;
    }


    protected function pollGenerateReport($session, $reportRequestId)
    {
        $request = new PollGenerateReportRequest();
        $request->ReportRequestId = $reportRequestId;

        return $session->GetService()->PollGenerateReport($request);
    }


    /**
     * toString()
     *
     *
     * @return string results
     */
    public function toString()
    {
        return $this->results ?? '';
    }

    /**
     * toArray()
     *
     *
     * @return array results
     */
    public function toArray()
    {
        $csv    = array_map('str_getcsv',$this->results);
        $header = $csv[10];

        // wtf is this bing??
        unset($csv[0]);
        unset($csv[1]);
        unset($csv[2]);
        unset($csv[3]);
        unset($csv[4]);
        unset($csv[5]);
        unset($csv[6]);
        unset($csv[7]);
        unset($csv[8]);
        unset($csv[9]);
        unset($csv[10]);

        // more wtf rows...
        array_pop($csv);
        array_pop($csv);

        $report = [];
        foreach($csv as $index=>$columns)
        {
            $r = [];
            foreach($columns as $index2=>$cs)
            {
                if (!isset($header[$index2])) continue;

                $n = $header[$index2];

                $r[$n] = str_replace(',','',$cs);
            }

            $report[] = $r;
        }

        ksort($report);

        return $report;
    }

}