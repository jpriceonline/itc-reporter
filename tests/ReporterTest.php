<?php
namespace Snscripts\ITCReporter\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Snscripts\ITCReporter\Reporter;

class ReporterTest extends TestCase
{
    public function testCanCreateInstance()
    {
        $this->assertInstanceOf(
            'Snscripts\ITCReporter\Reporter',
            new Reporter(
                new Client
            )
        );
    }

    public function testCanSetAndGetAccessToken()
    {
        $Reporter = new Reporter(
            new Client
        );

        $this->assertInstanceOf(
            'Snscripts\ITCReporter\Reporter',
            $Reporter->setAccessToken('12345678-1234-abcd-abcd-12345678abcd')
        );

        $this->assertSame(
            '12345678-1234-abcd-abcd-12345678abcd',
            $Reporter->getAccessToken()
        );
    }

    public function testSetAccessTokenThrowsExceptionWithInvalidData()
    {
        $this->expectException('InvalidArgumentException');

        $Reporter = new Reporter(
            new Client
        );

        $Reporter->setAccessToken(123);
        $Reporter->setAccessToken([]);
        $Reporter->setAccessToken(new \StdClass);
        $Reporter->setAccessToken('');
    }

    public function testCanSetAndGetAccount()
    {
        $Reporter = new Reporter(
            new Client
        );

        $this->assertInstanceOf(
            'Snscripts\ITCReporter\Reporter',
            $Reporter->setAccountNum(1234567)
        );

        $this->assertSame(
            1234567,
            $Reporter->getAccountNum()
        );
    }

    public function testSetAccountNumThrowsExceptionWithInvalidData()
    {
        $this->expectException('InvalidArgumentException');

        $Reporter = new Reporter(
            new Client
        );

        $Reporter->setAccountNum([]);
        $Reporter->setAccountNum(new \StdClass);
        $Reporter->setAccountNum('1234567');
    }

    public function testBuildJsonRequestBuildsCorrectForSalesGetAccounts()
    {
        $Reporter = new Reporter(
            new Client
        );

        $Reporter->setAccessToken('12345678-1234-abcd-abcd-12345678abcd');

        $this->assertSame(
            '{"accesstoken":"12345678-1234-abcd-abcd-12345678abcd","version":"2.1","mode":"Robot.XML","account":"None","queryInput":"[p=Reporter.properties, Sales.getAccounts]"}',
            $Reporter->buildJsonRequest('Sales.getAccounts')
        );
    }

    public function testBuildJsonRequestBuildsCorrectForSalesGetVendors()
    {
        $Reporter = new Reporter(
            new Client
        );

        $Reporter->setAccessToken('12345678-1234-abcd-abcd-12345678abcd')
            ->setAccountNum(1234567);

        $this->assertSame(
            '{"accesstoken":"12345678-1234-abcd-abcd-12345678abcd","version":"2.1","mode":"Robot.XML","account":"1234567","queryInput":"[p=Reporter.properties, Sales.getVendors]"}',
            $Reporter->buildJsonRequest('Sales.getVendors')
        );
    }

    public function testBuildJsonRequestBuildsCorrectForSalesGetReporter()
    {
        $Reporter = new Reporter(
            new Client
        );

        $Reporter->setAccessToken('12345678-1234-abcd-abcd-12345678abcd');

        $this->assertSame(
            '{"accesstoken":"12345678-1234-abcd-abcd-12345678abcd","version":"2.1","mode":"Robot.XML","account":"None","queryInput":"[p=Reporter.properties, Sales.getReport, 12345678,Sales,Summary,Daily,20161020]"}',
            $Reporter->buildJsonRequest('Sales.getReport', '12345678', 'Sales', 'Summary', 'Daily', '20161020')
        );
    }

    public function testBuildJsonRequestThrowsExceptionWhenNoDataPassed()
    {
        $this->expectException('BadFunctionCallException');

        $Reporter = new Reporter(
            new Client
        );

        $Reporter->setAccessToken('12345678-1234-abcd-abcd-12345678abcd')
            ->setAccountNum(1234567)
            ->buildJsonRequest();
    }

    public function testProcessResponseReturnsCorrectArray()
    {
        $action = 'Sales.getVendors';

        $WorkingResponse = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $WorkingResponse->method('getBody')
            ->willReturn(Utils::streamFor('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Vendors><Vendor>1234567</Vendor><Vendor>9876543</Vendor></Vendors>'));

        $Reporter = new Reporter(new Client);

        $this->assertSame(
            [
                1234567,
                9876543
            ],
            $Reporter->processResponse(
                $action,
                $WorkingResponse
            )
        );

        $BlankResponse = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $BlankResponse->method('getBody')
            ->willReturn(Utils::streamFor(''));

        $this->assertSame(
            [],
            $Reporter->processResponse(
                $action,
                $BlankResponse
            )
        );
    }

    public function testProcessResponseThrowsExceptionIfInvalidAction()
    {
        $this->expectException(\InvalidArgumentException::class);

        $BlankResponse = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $BlankResponse->method('getBody')
            ->willReturn(Utils::streamFor(''));

        $Reporter = new Reporter(new Client);

        $Reporter->processResponse('foobar', $BlankResponse);
    }

    public function testPerformRequestCanCheckStatusAndReturnCorrectly()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Vendors><Vendor>1234567</Vendor><Vendor>9876543</Vendor></Vendors>'));
        $Response->method('getStatusCode')
            ->willReturn(200);

        $GuzzleMock = $this->getMockBuilder('GuzzleHttp\ClientInterface')->getMock();
        $GuzzleMock->method('request')
            ->willReturn($Response);

        $Reporter = new Reporter($GuzzleMock);
        $Reporter->setAccessToken('12345678-1234-abcd-abcd-12345678abcd');

        $Result = $Reporter->performRequest(
            Reporter::SALESURL,
            $Reporter->buildJsonRequest('Sales.getVendors')
        );

        $this->assertInstanceOf(
            'Snscripts\Result\Result',
            $Result
        );

        $this->assertTrue(
            $Result->isSuccess()
        );

        $this->assertInstanceOf(
            'Psr\Http\Message\ResponseInterface',
            $Result->getExtra('Response')
        );

        $this->assertSame(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Vendors><Vendor>1234567</Vendor><Vendor>9876543</Vendor></Vendors>',
            $Result->getExtra('Response')->getBody()->getContents()
        );
    }

    public function testPerformRequestWhenResponseHasFailed()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor(''));
        $Response->method('getStatusCode')
            ->willReturn(404);

        $GuzzleMock = $this->getMockBuilder('GuzzleHttp\ClientInterface')->getMock();
        $GuzzleMock->method('request')
            ->willReturn($Response);

        $Reporter = new Reporter($GuzzleMock);
        $Reporter->setAccessToken('12345678-1234-abcd-abcd-12345678abcd');

        $Result = $Reporter->performRequest(
            Reporter::SALESURL,
            $Reporter->buildJsonRequest('Sales.getVendors')
        );

        $this->assertInstanceOf(
            'Snscripts\Result\Result',
            $Result
        );

        $this->assertTrue(
            $Result->isFail()
        );

        $this->assertSame(
            'The request did not return a 200 OK response',
            $Result->getMessage()
        );
    }

    public function testGetSalesAccountsReturnsCorrectArray()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Accounts><Account><Name>John Smith</Name><Number>1234567</Number></Account></Accounts>'));
        $Response->method('getStatusCode')
            ->willReturn(200);

        $GuzzleMock = $this->getMockBuilder('GuzzleHttp\ClientInterface')->getMock();
        $GuzzleMock->method('request')
            ->willReturn($Response);

        $Reporter = new Reporter($GuzzleMock);

        $this->assertSame(
            [
                1234567 => [
                    'Name' => 'John Smith',
                    'Number' => 1234567
                ]
            ],
            $Reporter->getSalesAccounts()
        );
    }

    public function testGetSalesAccountsReturnsBlankArrayOnFail()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor(''));
        $Response->method('getStatusCode')
            ->willReturn(404);

        $GuzzleMock = $this->getMockBuilder('GuzzleHttp\ClientInterface')->getMock();
        $GuzzleMock->method('request')
            ->willReturn(
                $Response
            );

        $Reporter = new Reporter(
            $GuzzleMock
        );

        $this->assertSame(
            [],
            $Reporter->getSalesAccounts()
        );
    }

    public function testGetSalesVendorsReturnsCorrectArray()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Vendors><Vendor>1234567</Vendor><Vendor>9876543</Vendor></Vendors>'));
        $Response->method('getStatusCode')
            ->willReturn(200);

        $GuzzleMock = $this->getMockBuilder('GuzzleHttp\ClientInterface')->getMock();
        $GuzzleMock->method('request')
            ->willReturn(
                $Response
            );

        $Reporter = new Reporter(
            $GuzzleMock
        );

        $this->assertSame(
            [
                1234567,
                9876543
            ],
            $Reporter->getSalesVendors()
        );
    }

    public function testGetSalesVendorsReturnsBlankArrayOnFail()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor(''));
        $Response->method('getStatusCode')
            ->willReturn(404);

        $GuzzleMock = $this->getMockBuilder('GuzzleHttp\ClientInterface')->getMock();
        $GuzzleMock->method('request')
            ->willReturn(
                $Response
            );

        $Reporter = new Reporter(
            $GuzzleMock
        );

        $this->assertSame(
            [],
            $Reporter->getSalesVendors()
        );
    }

    public function testGetFinanceAccountsReturnsCorrectArray()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Accounts><Account><Name>John Smith</Name><Number>1234567</Number></Account></Accounts>'));
        $Response->method('getStatusCode')
            ->willReturn(200);

        $GuzzleMock = $this->getMockBuilder('GuzzleHttp\ClientInterface')->getMock();
        $GuzzleMock->method('request')
            ->willReturn(
                $Response
            );

        $Reporter = new Reporter(
            $GuzzleMock
        );

        $this->assertSame(
            [
                1234567 => [
                    'Name' => 'John Smith',
                    'Number' => 1234567
                ]
            ],
            $Reporter->getFinanceAccounts()
        );
    }

    public function testGetFinanceAccountsReturnsBlankArrayOnFail()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor(''));
        $Response->method('getStatusCode')
            ->willReturn(404);

        $GuzzleMock = $this->getMockBuilder('GuzzleHttp\ClientInterface')->getMock();
        $GuzzleMock->method('request')
            ->willReturn(
                $Response
            );

        $Reporter = new Reporter(
            $GuzzleMock
        );

        $this->assertSame(
            [],
            $Reporter->getFinanceAccounts()
        );
    }

    public function testGetFinanceVendorsReturnsCorrectArray()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VendorsAndRegions><Vendor><Number>1234567</Number><Region><Code>AE</Code><Reports><Report>Financial</Report></Reports></Region><Region><Code>AU</Code><Reports><Report>Financial</Report><Report>Sale</Report></Reports></Region></Vendor></VendorsAndRegions>'));
        $Response->method('getStatusCode')
            ->willReturn(200);

        $GuzzleMock = $this->getMockBuilder('GuzzleHttp\ClientInterface')->getMock();
        $GuzzleMock->method('request')
            ->willReturn(
                $Response
            );

        $Reporter = new Reporter(
            $GuzzleMock
        );

        $this->assertSame(
            [
                1234567 => [
                    'Number' => 1234567,
                    'Regions' => [
                        'AE' => [
                            'Code' => 'AE',
                            'Reports' => [
                                'Financial'
                            ]
                        ],
                        'AU' => [
                            'Code' => 'AU',
                            'Reports' => [
                                'Financial',
                                'Sale'
                            ]
                        ]
                    ]
                ]
            ],
            $Reporter->getFinanceVendors()
        );
    }

    public function testGetFinanceVendorsReturnsBlankArrayOnFail()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor(''));
        $Response->method('getStatusCode')
            ->willReturn(404);

        $GuzzleMock = $this->getMockBuilder('GuzzleHttp\ClientInterface')->getMock();
        $GuzzleMock->method('request')
            ->willReturn(
                $Response
            );

        $Reporter = new Reporter(
            $GuzzleMock
        );

        $this->assertSame(
            [],
            $Reporter->getFinanceVendors()
        );
    }

    public function testGetSalesReportReturnsCorrectReport()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor((new TestSalesReportContent())->getContents()));
        $Response->method('getStatusCode')
            ->willReturn(200);

        $GuzzleMock = $this->getMockBuilder('GuzzleHttp\ClientInterface')->getMock();
        $GuzzleMock->method('request')
            ->willReturn($Response);

        $Reporter = new Reporter($GuzzleMock);

        $this->assertSame(
            [
                [
                    'Header 1' => 'Foo',
                    'Header 2' => 'Bar',
                    'Header 3' => 'Foobar'
                ],
                [
                    'Header 1' => 'Fizz',
                    'Header 2' => '',
                    'Header 3' => 'Fizzbuzz'
                ],
                [
                    'Header 1' => '',
                    'Header 2' => 'Test',
                    'Header 3' => 'Tester'
                ]
            ],
            $Reporter->getSalesReport(1234567, 'Sales', 'Summary', 'Daily', '20161025')
        );
    }

    public function testGetSalesReportReturnsBlankArrayWhenNoReport()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor((new TestSalesReportNoContent())->getContents()));
        $Response->method('getStatusCode')
            ->willReturn(404);

        $GuzzleMock = $this->getMockBuilder('GuzzleHttp\ClientInterface')->getMock();
        $GuzzleMock->method('request')
            ->willReturn($Response);

        $Reporter = new Reporter($GuzzleMock);

        $this->assertSame(
            [],
            $Reporter->getSalesReport(1234567, 'Sales', 'Summary', 'Daily', '20161025')
        );
    }

    public function testGetFinanceReportReturnsCorrectReport()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor((new TestFinanceReportContent())->getContents()));
        $Response->method('getStatusCode')
            ->willReturn(200);

        $GuzzleMock = $this->getMockBuilder('GuzzleHttp\ClientInterface')->getMock();
        $GuzzleMock->method('request')
            ->willReturn($Response);

        $Reporter = new Reporter($GuzzleMock);

        $this->assertSame(
            [
                [
                    'Header 1' => 'Foo',
                    'Header 2' => 'Bar',
                    'Header 3' => 'Foobar'
                ],
                [
                    'Header 1' => 'Fizz',
                    'Header 2' => '',
                    'Header 3' => 'Fizzbuzz'
                ],
                [
                    'Header 1' => '',
                    'Header 2' => 'Test',
                    'Header 3' => 'Tester'
                ],
                'Total' => '100',
                'Grand Total' => '500'
            ],
            $Reporter->getFinanceReport(1234567, 'GB', 'Financial', '2016', '1')
        );
    }

    public function testGetFinanceReportReturnsBlankArrayWhenNoReport()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor((new TestFinanceReportNoContent())->getContents()));
        $Response->method('getStatusCode')
            ->willReturn(404);

        $GuzzleMock = $this->getMockBuilder('GuzzleHttp\ClientInterface')->getMock();
        $GuzzleMock->method('request')
            ->willReturn($Response);

        $Reporter = new Reporter($GuzzleMock);

        $this->assertSame(
            [],
            $Reporter->getFinanceReport(1234567, 'GB', 'Financial', '2016', '1')
        );
    }

    public function testGetLastResultReturnsCorrectValue()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Vendors><Vendor>1234567</Vendor><Vendor>9876543</Vendor></Vendors>'));
        $Response->method('getStatusCode')
            ->willReturn(200);

        $GuzzleMock = $this->getMockBuilder('GuzzleHttp\ClientInterface')->getMock();
        $GuzzleMock->method('request')
            ->willReturn($Response);

        $Reporter = new Reporter($GuzzleMock);
        $Reporter->setAccessToken('12345678-1234-abcd-abcd-12345678abcd');

        // Assuming some method calls that would set the last result
        $Reporter->getSalesAccounts();  // This is just an example, adjust according to actual method that sets last result

        $this->assertInstanceOf(
            'Snscripts\Result\Result',
            $Reporter->getLastResult()
            );
    }
}

class TestSalesReportContent
{
    public function getContents()
    {
        $report = "Header 1\tHeader 2\tHeader 3\n\nFoo\tBar\tFoobar\nFizz\t\tFizzbuzz\n\tTest\tTester";
        return gzencode($report);
    }
}

class TestSalesReportNoContent
{
    public function getContents()
    {
        return '';
    }
}

class TestFinanceReportContent
{
    public function getContents()
    {
        $report = "Header 1\tHeader 2\tHeader 3\nFoo\tBar\tFoobar\n\nFizz\t\tFizzbuzz\n\tTest\tTester\nTotal\t100\nGrand Total\t500";
        return gzencode($report);
    }
}

class TestFinanceReportNoContent
{
    public function getContents()
    {
        return '';
    }
}