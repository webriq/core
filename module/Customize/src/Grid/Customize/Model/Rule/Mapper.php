<?php

namespace Grid\Customize\Model\Rule;

use Zend\Db\Sql;
use Zend\Db\Sql\Predicate;
use Zork\Db\Sql\Predicate\NotIn;
use Zork\Model\Structure\StructureAbstract;
use Zork\Model\Mapper\DbAware\ReadWriteMapperAbstract;

/**
 * Rule mapper
 *
 * @author David Pozsar <david.pozsar@megaweb.hu>
 */
class Mapper extends ReadWriteMapperAbstract
{

    /**
     * @var string
     */
    const PROPERTIES_FIELD = 'properties';

    /**
     * Table name used in all queries
     *
     * @var string
     */
    protected static $tableName = 'customize_rule';

    /**
     * Table name used additinally in select queries
     *
     * @var string
     */
    protected static $propertyTableName = 'customize_property';

    /**
     * Default column-conversion functions as types;
     * used in selected(), deselect()
     *
     * @var array
     */
    protected static $columns = array(
        'id'                => self::INT,
        'media'             => self::STR,
        'selector'          => self::STR,
        'rootParagraphId'   => self::INT,
    );

    /**
     * Contructor
     *
     * @param \Customize\Model\Rule\Structure $customizeRuleStructurePrototype
     */
    public function __construct( Structure $customizeRuleStructurePrototype = null )
    {
        parent::__construct( $customizeRuleStructurePrototype ?: new Structure );
    }

    /**
     * Get select() default columns
     *
     * @return array
     */
    protected function getSelectColumns( $columns = null )
    {
        if ( null === $columns )
        {
            $properties = true;
        }
        elseif ( ( $index = array_search( self::PROPERTIES_FIELD, $columns ) ) )
        {
            $properties = true;
            unset( $columns[$index] );
        }
        else
        {
            $properties = false;
        }

        $columns = parent::getSelectColumns( $columns );

        if ( $properties )
        {
            $platform = $this->getDbAdapter()
                             ->getPlatform();

            $columns[self::PROPERTIES_FIELD] = new Sql\Expression( '(' .
                $this->sql( $this->getTableInSchema(
                        static::$propertyTableName
                     ) )
                     ->select()
                     ->columns( array(
                         new Sql\Expression( 'TEXT( ARRAY_TO_JSON(
                             ARRAY_AGG( ? ORDER BY CHAR_LENGTH( ? ) ASC )
                         ) )', array(
                             static::$propertyTableName,
                             'name',
                         ), array(
                             Sql\Expression::TYPE_IDENTIFIER,
                             Sql\Expression::TYPE_IDENTIFIER,
                         ) )
                     ) )
                     ->where( array(
                         new Predicate\Expression(
                             $platform->quoteIdentifier( 'ruleId' ) .
                             ' = ' .
                             $platform->quoteIdentifierChain( array(
                                 static::$tableName, 'id'
                             ) )
                         )
                     ) )
                     ->getSqlString( $platform ) .
            ')' );
        }

        return $columns;
    }

    /**
     * Get paginator
     *
     * @param   mixed|null  $where
     * @param   mixed|null  $order
     * @param   mixed|null  $columns
     * @param   mixed|null  $joins
     * @param   mixed|null  $quantifier
     * @return  \Zend\Paginator\Paginator
     */
    public function getPaginator( $where        = null,
                                  $order        = null,
                                  $columns      = null,
                                  $joins        = null,
                                  $quantifier   = null )
    {
        $joins = array_merge( (array) $joins, array(
            'rootParagraph' => array(
                'table'     => array(
                    'rootParagraph' => $this->getTableInSchema( 'paragraph' )
                ),
                'where'     => 'customize_rule.rootParagraphId = rootParagraph.id',
                'type'      => Sql\Select::JOIN_LEFT,
                'columns'   => array(
                    'rootType'  => 'type',
                    'rootName'  => 'name',
                ),
            ),
        ) );

        return parent::getPaginator( $where, $order, $columns, $joins, $quantifier );
    }

    /**
     * Parse properties
     *
     * Like:
     * <pre>
     * &lt;struct&gt;
     * [{"name":"{key}","value":"{value}","priority":"{priority}"}]
     * &nbsp;...
     * &lt;/struct&gt;
     * </pre>
     *
     * @param string $properties
     * @return array
     */
    protected function parseProperties( & $properties )
    {
        if ( empty( $properties ) )
        {
            return array();
        }

        $result = array();
        foreach ( json_decode( $properties, true ) as $property )
        {
            $name = (string) $property['name'];

            $result[$name] = array(
                'name'      => $name,
                'value'     => $property['value'],
                'priority'  => $property['priority'],
            );
        }

        return $result;
    }

    /**
     * Transforms the selected data into the structure object
     *
     * @param array $data
     * @return \Customize\Model\Rule\Structure
     */
    public function selected( array $data )
    {
        if ( isset( $data[self::PROPERTIES_FIELD] ) &&
             is_string( $data[self::PROPERTIES_FIELD] ) )
        {
            $data[self::PROPERTIES_FIELD] =
                $this->parseProperties( $data[self::PROPERTIES_FIELD] );
        }

        return parent::selected( $data );
    }

    /**
     * Hydrate $object with the provided $data.
     *
     * @param  array $data
     * @param  object $structure
     * @return object
     */
    public function hydrate( array $data, $structure )
    {
        if ( $structure instanceof Structure )
        {
            if ( isset( $data[self::PROPERTIES_FIELD] ) )
            {
                $properties = $data[self::PROPERTIES_FIELD] ?: array();
                unset( $data[self::PROPERTIES_FIELD] );

                if ( is_string( $properties ) )
                {
                    $properties = $this->parseProperties( $properties );
                }

                if ( is_array( $properties ) )
                {
                    $data[self::PROPERTIES_FIELD] = $properties;
                }
            }
            else
            {
                $data[self::PROPERTIES_FIELD] = array();
            }
        }

        return parent::hydrate( $data, $structure );
    }

    /**
     * Save a single property
     *
     * @param int $id
     * @param string $name
     * @param mixed $value
     * @param mixed $priority
     * @return int
     */
    protected function saveProperty( $id, $name, $value, $priority )
    {
        $sql = $this->sql( $this->getTableInSchema(
            static::$propertyTableName
        ) );

        $update = $sql->update()
                      ->set( array(
                          'value'       => $value,
                          'priority'    => $priority,
                      ) )
                      ->where( array(
                          'ruleId'      => $id,
                          'name'        => $name,
                      ) );

        $result = $sql->prepareStatementForSqlObject( $update )
                      ->execute();

        $affected = $result->getAffectedRows();

        if ( $affected < 1 )
        {
            $insert = $sql->insert()
                          ->values( array(
                              'ruleId'      => $id,
                              'name'        => $name,
                              'value'       => $value,
                              'priority'    => $priority,
                          ) );

            $affected = $sql->prepareStatementForSqlObject( $insert )
                            ->execute()
                            ->getAffectedRows();
        }

        return $affected;
    }

    /**
     * @param   mixed   $structure
     * @param   string  $property
     * @param   mixed   $default
     * @return  mixed
     */
    private function getSaveProperty( & $structure, $property, $default = null )
    {
        return is_array( $structure )
            ? ( empty( $structure[$property] ) ? $default : $structure[$property] )
            : ( empty( $structure->$property ) ? $default : $structure->$property );
    }

    /**
     * @param   mixed   $structure
     * @param   string  $property
     * @param   mixed   $value
     * @return  void
     */
    private function setSaveProperty( & $structure, $property, $value )
    {
        if ( $structure instanceof StructureAbstract )
        {
            $structure->setOption( $property, $value );
        }
        else if ( is_array( $structure ) )
        {
            $structure[$property] = $value;
        }
        else
        {
            $structure->$property = $value;
        }
    }

    /**
     * Save element structure to datasource
     *
     * @param array|\Customize\Model\Rule\Structure $structure
     * @return int Number of affected rows
     */
    public function save( & $structure )
    {
        $id = $this->getSaveProperty( $structure, 'id' );

        if ( empty( $id ) )
        {
            $selector = $this->getSaveProperty( $structure, 'selector', '' );
            $media    = $this->getSaveProperty( $structure, 'media', '' );
            $rootId   = $this->getSaveProperty( $structure, 'rootParagraphId' );
            $sql      = $this->sql();
            $select   = $sql->select()
                            ->columns( array( 'id' ) )
                            ->where( array(
                                'selector'        => $selector,
                                'media'           => $media,
                                'rootParagraphId' => $rootId,
                            ) )
                            ->limit( 1 );

            $result = $sql->prepareStatementForSqlObject( $select )
                          ->execute();

            if ( $result->getAffectedRows() )
            {
                foreach ( $result as $row )
                {
                    $this->setSaveProperty( $structure, 'id', $row['id'] );
                }
            }
        }

        $result = parent::save( $structure );
        $id     = $this->getSaveProperty( $structure, 'id' );

        if ( $result > 0 && ! empty( $id ) )
        {
            switch ( true )
            {
                case $structure instanceof Structure:
                    $properties = $structure->getRawProperties();
                    break;

                case is_array( $structure ):
                    $properties = isset( $structure['properties'] )
                        ? $structure['properties']
                        : array();
                    break;

                case is_object( $structure ):
                    $properties = isset( $structure->properties )
                        ? $structure->properties
                        : array();
                    break;

                default:
                    return $result;
            }

            $sql = $this->sql( $this->getTableInSchema(
                static::$propertyTableName
            ) );

            if ( empty( $properties ) )
            {
                $delete = $sql->delete()
                              ->where( array(
                                  'ruleId' => $id,
                              ) );

                $result += $sql->prepareStatementForSqlObject( $delete )
                               ->execute()
                               ->getAffectedRows();
            }
            else
            {
                $propNames = array();

                foreach ( $properties as $name => $spec )
                {
                    $propNames[] = $propName = empty( $spec['name'] )
                        ? $name : $spec['name'];

                    $result += $this->saveProperty(
                        $id,
                        $propName,
                        $spec['value'],
                        $spec['priority']
                    );
                }

                $delete = $sql->delete()
                              ->where( array(
                                  'ruleId' => $id,
                                  new NotIn( 'name', $propNames ),
                              ) );

                $result += $sql->prepareStatementForSqlObject( $delete )
                               ->execute()
                               ->getAffectedRows();
            }
        }

        return $result;
    }

    /**
     * Find structure by selector & media
     *
     * @param string $selector
     * @param string $media [optional]
     * @param int $rootId [optional]
     * @return \Customize\Model\Rule\Structure
     */
    public function findBySelector( $selector, $media = '', $rootId = null )
    {
        $where = array(
            'selector'  => (string) $selector,
            'media'     => (string) $media,
        );

        if ( empty( $rootId ) )
        {
            $where[] = new Predicate\IsNull( 'rootParagraphId' );
        }
        else
        {
            $where['rootParagraphId'] = (int) $rootId;
        }

        return $this->findOne( $where );
    }

    /**
     * Find all structure by rootId
     *
     * @param null|int $rootId
     * @param null|array $order
     * @param null|int $limit
     * @param null|int $offset
     * @return \Customize\Model\Rule\Structure[]
     */
    public function findAllByRoot( $rootId  = null,
                                   $order   = null,
                                   $limit   = null,
                                   $offset  = null )
    {
        $select = $this->select();

        if ( empty( $rootId ) )
        {
            $where = array(
                new Predicate\IsNull( 'rootParagraphId' )
            );
        }
        else
        {
            $where = array(
                'rootParagraphId' => (int) $rootId,
            );
        }

        $select->where( $where )
               ->order( $order ?: array() )
               ->limit( $limit )
               ->offset( $offset );

        /* @var $result \Zend\Db\Adapter\Driver\ResultInterface */

        $return = array();
        $result = $this->sql()
                       ->prepareStatementForSqlObject( $select )
                       ->execute();

        foreach ( $result as $row )
        {
            $return[] = $this->selected( $row );
        }

        return $return;
    }

    /**
     * Delete rules by root-id
     *
     * @param   int|null    $rootId
     * @param   int[]       $except
     * @return  int
     */
    public function deleteByRoot( $rootId = null, array $except = array() )
    {
        if ( null === $rootId )
        {
            $where = array( new Predicate\IsNull(
                'rootParagraphId'
            ) );
        }
        else
        {
            $where = array(
                'rootParagraphId' => (int) $rootId,
            );
        }

        if ( ! empty( $except ) )
        {
            $where[] = new Predicate\NotIn( 'id', $except );
        }

        $delete = $this->sql()
                       ->delete()
                       ->where( $where );

        $result = $this->sql()
                       ->prepareStatementForSqlObject( $delete )
                       ->execute();

        return $result->getAffectedRows();
    }

    /**
     * Is selector (at a media) exists
     *
     * @param   string      $selector
     * @param   string      $media
     * @param   int|null    $rootId
     * @param   int|null    $excludeId
     * @return  bool
     */
    public function isSelectorExists( $selector,
                                      $media        = '',
                                      $rootId       = null,
                                      $excludeId    = null )
    {
        $where = array(
            'selector'  => (string) $selector,
            'media'     => (string) $media,
        );

        if ( empty( $rootId ) )
        {
            $where[] = new Predicate\IsNull( 'rootParagraphId' );
        }
        else
        {
            $where['rootParagraphId'] = (int) $rootId;
        }

        if ( ! empty( $excludeId ) )
        {
            $where[] = new Predicate\Operator(
                'id',
                Predicate\Operator::OP_NE,
                $excludeId
            );
        }

        return $this->isExists( $where );
    }

}
