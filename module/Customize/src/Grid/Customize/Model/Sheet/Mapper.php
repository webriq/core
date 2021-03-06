<?php

namespace Grid\Customize\Model\Sheet;

use Zend\Db\Sql\Select;
use Zork\Db\Sql\Values;
use Zend\Db\Sql\Expression;
use Zend\Stdlib\ArrayUtils;
use Zork\Model\MapperAwareInterface;
use Zork\Db\Sql\Predicate\TypedParameters;
use Zend\Stdlib\Hydrator\HydratorInterface;
use Zork\Model\Mapper\ReadWriteMapperInterface;
use Grid\Customize\Model\Rule\Mapper as RuleMapper;
use Grid\Customize\Model\Extra\Mapper as ExtraMapper;
use Grid\Customize\Model\Extra\Structure as ExtraStructure;
use Grid\Paragraph\Model\Paragraph\Mapper as ParagraphMapper;

/**
 * Mapper
 *
 * @author David Pozsar <david.pozsar@megaweb.hu>
 */
class Mapper implements ReadWriteMapperInterface, HydratorInterface
{

    /**
     * @var RuleMapper
     */
    protected $ruleMapper;

    /**
     * @var ExtraMapper
     */
    protected $extraMapper;

    /**
     * @var ParagraphMapper
     */
    protected $paragraphMapper;

    /**
     * Structure prototype for the mapper
     *
     * @var Structure
     */
    protected $structurePrototype;

    /**
     * Get rule mapper
     *
     * @return  RuleMapper
     */
    public function getRuleMapper()
    {
        return $this->ruleMapper;
    }

    /**
     * Set rule mapper
     *
     * @param   RuleMapper $ruleMapper
     * @return  Mapper
     */
    public function setRuleMapper( RuleMapper $ruleMapper )
    {
        $this->ruleMapper = $ruleMapper;
        return $this;
    }

    /**
     * Get extra mapper
     *
     * @return  ExtraMapper
     */
    public function getExtraMapper()
    {
        return $this->extraMapper;
    }

    /**
     * Set extra mapper
     *
     * @param   ExtraMapper $extraMapper
     * @return  Mapper
     */
    public function setExtraMapper( ExtraMapper $extraMapper )
    {
        $this->extraMapper = $extraMapper;
        return $this;
    }

    /**
     * Get paragraph mapper
     *
     * @return  ExtraMapper
     */
    public function getParagraphMapper()
    {
        return $this->paragraphMapper;
    }

    /**
     * Set paragraph mapper
     *
     * @param   ParagraphMapper $paragraphMapper
     * @return  Mapper
     */
    public function setParagraphMapper( ParagraphMapper $paragraphMapper )
    {
        $this->paragraphMapper = $paragraphMapper;
        return $this;
    }

    /**
     * Get structure prototype
     *
     * @return  Structure
     */
    public function getStructurePrototype()
    {
        return $this->structurePrototype;
    }

    /**
     * Set structure prototype
     *
     * @param   Structure $structurePrototype
     * @return  Mapper
     */
    public function setStructurePrototype( $structurePrototype )
    {
        if ( $structurePrototype instanceof MapperAwareInterface )
        {
            $structurePrototype->setMapper( $this );
        }

        $this->structurePrototype = $structurePrototype;
        return $this;
    }

    /**
     * Constructor
     *
     * @param   RuleMapper      $customizeRuleMapper
     * @param   ExtraMapper     $customizeExtraMapper
     * @param   ParagraphMapper $paragraphMapper
     * @param   Structure       $customizeSheetStructurePrototype
     */
    public function __construct( RuleMapper      $customizeRuleMapper,
                                 ExtraMapper     $customizeExtraMapper,
                                 ParagraphMapper $paragraphMapper,
                                 Structure       $customizeSheetStructurePrototype)
    {
        $this->setRuleMapper( $customizeRuleMapper )
             ->setExtraMapper( $customizeExtraMapper )
             ->setParagraphMapper( $paragraphMapper )
             ->setStructurePrototype( $customizeSheetStructurePrototype );
    }

    /**
     * Create structure from plain data
     *
     * @param   array   $data
     * @return  Structure
     */
    protected function createStructure( array $data )
    {
        $structure = clone $this->structurePrototype;
        $structure->setOptions( $data );

        if ( $structure instanceof MapperAwareInterface )
        {
            $structure->setMapper( $this );
        }

        return $structure;
    }

    /**
     * Create structure from plain data
     *
     * @param array|\Traversable $data
     * @return \Zork\Model\Structure\StructureAbstract
     */
    public function create( $data )
    {
        $data = ArrayUtils::iteratorToArray( $data );
        return $this->createStructure( $data );
    }

    /**
     * @return array
     */
    protected function getRuleOrder()
    {
        return array(
            new Expression(
                'CHAR_LENGTH( ? ) ASC',
                array( 'media' ),
                array( Expression::TYPE_IDENTIFIER )
            ),
            new Expression(
                '? DESC',
                array( 'media' ),
                array( Expression::TYPE_IDENTIFIER )
            ),
            new Expression(
                'CHAR_LENGTH( ? ) ASC',
                array( 'selector' ),
                array( Expression::TYPE_IDENTIFIER )
            ),
            new Expression(
                '? ASC',
                array( 'selector' ),
                array( Expression::TYPE_IDENTIFIER )
            ),
        );
    }

    /**
     * Get the complete structure
     *
     * @deprecated
     * @return  Structure
     */
    public function findComplete()
    {
        return $this->createStructure( array(
            'rules' => $this->getRuleMapper()
                            ->findAll( array(), $this->getRuleOrder() ),
        ) );
    }

    /**
     * Find a structure by root paragraph id
     *
     * @param   int|array   $primaryKeys
     * @return  Structure
     */
    public function find( $primaryKeys )
    {
        if ( is_array( $primaryKeys ) )
        {
            $primaryKeys = reset( $primaryKeys );
        }

        $rootId = ( (int) $primaryKeys ) ?: null;

        return $this->createStructure( array(
            'rootId'    => $rootId,
            'extra'     => $this->getExtraMapper()
                                ->findByRoot( $rootId ),
            'rules'     => $this->getRuleMapper()
                                ->findAllByRoot(
                                    $rootId,
                                    $this->getRuleOrder()
                                ),
        ) );
    }

    /**
     * Find a structure by its extra structure
     *
     * @param   int|array   $primaryKeys
     * @return  Structure
     */
    public function findByExtra( ExtraStructure $extra )
    {
        $rootId = $extra->rootParagraphId;

        return $this->createStructure( array(
            'rootId'    => $rootId,
            'extra'     => $extra,
            'rules'     => $this->getRuleMapper()
                                ->findAllByRoot(
                                    $rootId,
                                    $this->getRuleOrder()
                                ),
        ) );
    }

    /**
     * Get paginator for listing (roots only)
     *
     * @return  \Zend\Paginator\Paginator
     */
    public function getPaginator()
    {
        return $this->getParagraphMapper()
                    ->getPaginator(
                        array(
                            new TypedParameters(
                                '?.? = ?.? OR ?.? IS NULL',
                                array(
                                    'paragraph', 'id',
                                    'paragraph', 'rootId',
                                    'paragraph', 'id',
                                ),
                                array(
                                    TypedParameters::TYPE_IDENTIFIER,
                                    TypedParameters::TYPE_IDENTIFIER,
                                    TypedParameters::TYPE_IDENTIFIER,
                                    TypedParameters::TYPE_IDENTIFIER,
                                    TypedParameters::TYPE_IDENTIFIER,
                                    TypedParameters::TYPE_IDENTIFIER,
                                )
                            ),
                        ),
                        array(
                            new Expression(
                                'COALESCE( ?.?, 0 ) ASC',
                                array(
                                    'paragraph', 'id',
                                ),
                                array(
                                    Expression::TYPE_IDENTIFIER,
                                    Expression::TYPE_IDENTIFIER,
                                )
                            ),
                        ),
                        null,
                        array(
                            'customize_global' => array(
                                'type'      => 'FULL OUTER',
                                'table'     => array(
                                    'customize_global' => new Values( array( null ) )
                                ),
                                'where'     => new Expression( 'FALSE' ),
                                'columns'   => array(),
                            ),
                            'customize_extra' => array(
                                'type'      => Select::JOIN_LEFT,
                                'table'     => 'customize_extra',
                                'where'     => new Expression(
                                    '?.? IS NOT DISTINCT FROM ?.?',
                                    array(
                                        'customize_extra', 'rootParagraphId',
                                        'paragraph', 'id',
                                    ),
                                    array(
                                        Expression::TYPE_IDENTIFIER,
                                        Expression::TYPE_IDENTIFIER,
                                        Expression::TYPE_IDENTIFIER,
                                        Expression::TYPE_IDENTIFIER,
                                    )
                                ),
                                'columns'   => array(
                                    'updated'   => 'updated',
                                ),
                            ),
                        )
                    );
    }

    /**
     * @param   int|null    $rootId
     * @param   array       $rules
     * @return  int
     */
    protected function saveRules( $rootId, array $rules )
    {
        $result = 0;
        $except = array();
        $mapper = $this->getRuleMapper();

        foreach ( $rules as $rule )
        {
            $rule->rootParagraphId = $rootId;
            $result += $mapper->save( $rule );
            $except[] = $rule->id;
        }

        $result += $this->getRuleMapper()
                        ->deleteByRoot( $rootId, $except );

        return $result;
    }

    /**
     * @param   int|null    $rootId
     * @param   string      $extra
     * @return  int
     */
    protected function saveExtra( $rootId, $extra )
    {
        $mapper     = $this->getExtraMapper();
        $structure  = $mapper->findByRoot( $rootId );

        if ( empty( $structure ) )
        {
            $structure = $mapper->create( array(
                'rootParagraphId' => $rootId,
            ) );
        }

        $structure->extra = $extra;
        return $mapper->save( $structure );
    }

    /**
     * Save a structure
     *
     * @param   array|Structure $structure
     * @return  int
     */
    public function save( &$structure )
    {
        if ( is_object( $structure ) )
        {
            $data = $structure;
        }
        else if ( is_array( $structure ) )
        {
            $data = (object) $structure;
        }
        else
        {
            return 0;
        }

        $result = 0;
        $rootId = isset( $data->rootId )        ? (int) $data->rootId   : null;
        $rules  = isset( $data->rules )         ? (array) $data->rules  : null;
        $extra  = isset( $data->extraContent )  ? $data->extraContent   : null;

        $result += $this->saveRules( $rootId ?: null, $rules );
        $result += $this->saveExtra( $rootId ?: null, $extra );
        return $result;
    }

    /**
     * Remove a structure
     *
     * @param   int|string|array|Structure  $structureOrPrimaryKeys
     * @return  int
     */
    public function delete( $structureOrPrimaryKeys )
    {
        if ( $structureOrPrimaryKeys instanceof Structure )
        {
            $structure = $structureOrPrimaryKeys->resetContents();
        }
        else if ( null === $structureOrPrimaryKeys ||
                is_scalar( $structureOrPrimaryKeys ) )
        {
            $structure = $this->createStructure( array(
                'rootId' => $structureOrPrimaryKeys,
            ) );
        }
        else
        {
            $structureOrPrimaryKeys = (array) $structureOrPrimaryKeys;
            $structure = $this->createStructure( array(
                'rootId' => isset( $structureOrPrimaryKeys['rootId'] )
                            ? $structureOrPrimaryKeys['rootId']
                            : reset( $structureOrPrimaryKeys )
            ) );
        }

        return $this->save( $structure );
    }

    /**
     * Extract values from an object
     *
     * @param   object  $object
     * @return  array
     */
    public function extract( $object )
    {
        if ( $object instanceof Structure )
        {
            return array(
                'rootId'    => $object->rootId,
                'css'       => $object->render()
                                      ->getBuffer(),
            );
        }

        return array(
            'rootId'    => isset( $object->rootId )
                            ? (string) $object->rootId
                            : null,
            'css'       => isset( $object->css )
                            ? (string) $object->css
                            : null,
        );
    }

    /**
     * Hydrate $object with the provided $data.
     *
     * @param   array   $data
     * @param   object  $object
     * @return  object
     */
    public function hydrate( array $data, $object )
    {
        if ( $object instanceof MapperAwareInterface )
        {
            $object->setMapper( $this );
        }

        if ( $object instanceof Structure )
        {
            if ( ! empty( $data['cssFile'] ) && is_file( $data['cssFile'] ) )
            {
                $object->parseFile( $data['cssFile'] );
            }
            else if ( ! empty( $data['css'] ) )
            {
                $object->parseString( $data['css'] );
            }
        }
        else
        {
            if ( ! empty( $data['cssFile'] ) && is_file( $data['cssFile'] ) )
            {
                $object->css = file_get_contents( $data['cssFile'] );
            }
            else if ( ! empty( $data['css'] ) )
            {
                $object->css = (string) $data['css'];
            }
        }

        if ( array_key_exists( 'rootId', $data ) )
        {
            $object->rootId = $data['rootId'];
        }

        return $object;
    }

}
