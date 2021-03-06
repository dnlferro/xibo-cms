<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (CampaignTest.php)
 */

namespace Xibo\Tests\Integration;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboCampaign;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class CampaignTest
 * @package Xibo\Tests
 */
class CampaignTest extends LocalWebTestCase
{

    protected $startCampaigns;

    /**
     * setUp - called before every test automatically
     */

    public function setup()
    {  
        parent::setup();
        $this->startCampaigns = (new XiboCampaign($this->getEntityProvider()))->get();
    }

    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all campaigns that weren't there initially
        $finalCamapigns = (new XiboCampaign($this->getEntityProvider()))->get(['start' => 0, 'length' => 1000]);
        # Loop over any remaining campaigns and nuke them
        foreach ($finalCamapigns as $campaign) {
            /** @var XiboCampaign $campaign */
            $flag = true;
            foreach ($this->startCampaigns as $startCampaign) {
               if ($startCampaign->campaignId == $campaign->campaignId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $campaign->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $campaign->campaignId . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }

    /**
     * Show Campaigns
     */
    public function testListAll()
    {
        $this->client->get('/campaign');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
    }

    /**
     * Add Campaign
     * @return mixed
     */
    public function testAdd()
    {
        $name = Random::generateString(8, 'phpunit');

        $this->client->post('/campaign', [
            'name' => $name
        ]);

        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($name, $object->data->campaign);

        return $object->id;
    }

    /**
     * Test edit
     * @depends testAdd
     */
    public function testEdit()
    {
        $name = Random::generateString(8, 'phpunit');
        $campaign = (new XiboCampaign($this->getEntityProvider()))->create($name);

        $newName = Random::generateString(8, 'phpunit');
        
        $this->client->put('/campaign/' . $campaign->campaignId, [
            'name' => $newName
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status());

        $object = json_decode($this->client->response->body());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertSame($newName, $object->data->campaign);
    }

    /**
     * @depends testEdit
     */
    public function testDelete()
    {
        $name1 = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        # Load in a couple of known campaigns
        $camp1 = (new XiboCampaign($this->getEntityProvider()))->create($name1);
        $camp2 = (new XiboCampaign($this->getEntityProvider()))->create($name2);
        # Delete the one we created last
        $this->client->delete('/campaign/' . $camp2->campaignId);
        # This should return 204 for success
        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());
        # Check only one remains
        $campaigns = (new XiboCampaign($this->getEntityProvider()))->get();
        $this->assertEquals(count($this->startCampaigns) + 1, count($campaigns));
        $flag = false;
        foreach ($campaigns as $campaign) {
            if ($campaign->campaignId == $camp1->campaignId) {
                $flag = true;
            }
        }
        $this->assertTrue($flag, 'Campaign ID ' . $camp1->campaignId . ' was not found after deleting a different campaign');
        $camp1->delete();
    }

    /**
     * Assign Layout
     * @throws \Xibo\Exception\NotFoundException
     */
    public function testAssignUnassignLayout()
    {
        // Make a campaign with a known name
        $name = Random::generateString(8, 'phpunit');

        /* @var XiboCampaign $campaign */
        $campaign = (new XiboCampaign($this->getEntityProvider()))->create($name);

        // Get a layout for the test
        $layout = (new XiboLayout($this->getEntityProvider()))->create('phpunit layout', 'phpunit description', '', 9);

        $this->assertGreaterThan(0, count($layout), 'Cannot find layout for test');

        // Call assign on the default layout
        $this->client->post('/campaign/layout/assign/' . $campaign->campaignId, [
            'layoutId' => [
                [
                    'layoutId' => $layout->layoutId,
                    'displayOrder' => 1
                ]
            ]
        ]);

        $this->assertSame(200, $this->client->response->status(), '/campaign/layout/assign/' . $campaign->campaignId . '. Body: ' . $this->client->response->body());

        # Get this campaign and check it has 1 layout assigned
        $campaignCheck = (new XiboCampaign($this->getEntityProvider()))->getById($campaign->campaignId);

        $this->assertSame($campaign->campaignId, $campaignCheck->campaignId, $this->client->response->body());
        $this->assertSame(1, $campaignCheck->numberLayouts, $this->client->response->body());

        # Call unassign on the created layout
        $this->client->post('/campaign/layout/unassign/' . $campaign->campaignId, ['layoutId' => [['layoutId' => $layout->layoutId, 'displayOrder' => 1]]]);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());

        # Get this campaign and check it has 0 layouts assigned
        $campaignCheck2 = (new XiboCampaign($this->getEntityProvider()))->getById($campaign->campaignId);

        $this->assertSame($campaign->campaignId, $campaignCheck2->campaignId, $this->client->response->body());
        $this->assertSame(0, $campaignCheck2->numberLayouts, $this->client->response->body());

        # delete layout as we no longer need it
        $layout->delete();
    }
}
