<?php
namespace Snscripts\ITCReporter\Tests\Responses;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Snscripts\ITCReporter\Responses\FinanceGetVendors;

class FinanceGetVendorsTest extends TestCase
{
    public function testProcessReturnsCorrectValueForSingleFinanceVendor()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VendorsAndRegions><Vendor><Number>1234567</Number><Region><Code>AE</Code><Reports><Report>Financial</Report></Reports></Region><Region><Code>AU</Code><Reports><Report>Financial</Report><Report>Sale</Report></Reports></Region></Vendor></VendorsAndRegions>'));

        $Processor = new FinanceGetVendors(
            $Response
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
            $Processor->process()
        );
    }

    public function testProcessReturnsCorrectValueForMultipleFinanceVendor()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VendorsAndRegions><Vendor><Number>1234567</Number><Region><Code>AE</Code><Reports><Report>Financial</Report></Reports></Region><Region><Code>AU</Code><Reports><Report>Financial</Report><Report>Sale</Report></Reports></Region></Vendor><Vendor><Number>9876543</Number><Region><Code>EU</Code><Reports><Report>Financial</Report><Report>Sale</Report></Reports></Region></Vendor></VendorsAndRegions>'));

        $Processor = new FinanceGetVendors(
            $Response
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
                ],
                9876543 => [
                    'Number' => 9876543,
                    'Regions' => [
                        'EU' => [
                            'Code' => 'EU',
                            'Reports' => [
                                'Financial',
                                'Sale'
                            ]
                        ]
                    ]
                ]
            ],
            $Processor->process()
        );
    }

    public function testProcessReturnsEmptyArrayForInvalidXML()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><VendorsAndRegions>Number>1234567</Number><Region><Code>AE</Code><Reports><Report>Financial</Report></Reports></Region>AU</Code><Reports><Report>Financial</Report><Report>Sale</Report></Reports></Region></Vendor></VendorsAndRegions>'));

        $Processor = new FinanceGetVendors(
            $Response
        );

        $this->assertSame(
            [],
            $Processor->process()
        );
    }

    public function testProcessReturnsEmptyArrayForEmptyContents()
    {
        $Response = $this->getMockBuilder('Psr\Http\Message\ResponseInterface')->getMock();
        $Response->method('getBody')
            ->willReturn(Utils::streamFor(''));

        $Processor = new FinanceGetVendors(
            $Response
        );

        $this->assertSame(
            [],
            $Processor->process()
        );
    }
}
