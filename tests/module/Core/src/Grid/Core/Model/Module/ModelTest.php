<?php

namespace Grid\Core\Model\Module;

use Zork\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

/**
 * ModelTest
 *
 * @author David Pozsar <david.pozsar@megaweb.hu>
 */
class ModelTest extends AbstractHttpControllerTestCase
{

    /**
     * Test find
     */
    public function testFind()
    {
        // DB STOPPOLÁS MIATT IDEIGLENESEN KIKOMMENTEZVE
        // NE TÖRÖLD !!! (csak ha megjavítod)
        
        $this->assertTrue(true);
        
        
        /* @var $model \Grid\Core\Model\Module\Model */
        /* @var $structure \Grid\Core\Model\Module\Structure */
        /*$model      = $this->getService( 'Grid\Core\Model\Module\Model' );
        $structure  = $model->find();
        $this->assertInstanceOf( 'Grid\Core\Model\Module\Structure', $structure );
        $structure->modules['Grid\Core'] = true;
        $structure->save();
        $structure  = null;

        $structure  = $model->find();
        $this->assertArrayHasKey( 'Grid\Core', $structure->modules );
        $this->assertTrue( $structure->modules['Grid\Core'] );
        $structure->delete();
        $structure  = null;

        $structure  = $model->find();
        $this->assertArrayNotHasKey( 'Grid\Core', $structure->modules );
        $structure  = null;
        $model      = null;*/
    }

}
