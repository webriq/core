<?php

namespace Grid\Core\Model\Uri;

use Locale;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Predicate;
use Zend\Db\Sql\Expression;
use Zork\Model\Mapper\DbAware\ReadWriteMapperAbstract;

/**
 * Mapper
 *
 * @author David Pozsar <david.pozsar@megaweb.hu>
 */
class Mapper extends ReadWriteMapperAbstract
{

    /**
     * Table name used in all queries
     *
     * @var string
     */
    protected static $tableName = 'content_uri';

    /**
     * Default column-conversion functions as types;
     * used in selected(), deselect()
     *
     * @var array
     */
    protected static $columns = array(
        'id'                => self::INT,
        'subdomainId'       => self::INT,
        'contentId'         => self::INT,
        'locale'            => self::STR,
        'uri'               => self::STR,
        'default'           => self::BOOL,
    );

    /**
     * Contructor
     *
     * @param   \Grid\Core\Model\Uri\Structure $uriStructurePrototype
     */
    public function __construct( Structure $uriStructurePrototype = null )
    {
        parent::__construct( $uriStructurePrototype ?: new Structure );
    }

    /**
     * Get by subdomain & uri
     *
     * @param   int     $subdomainId
     * @param   string  $uri
     * @return  \Grid\Core\Model\Uri\Structure|null
     */
    public function findBySubdomainUri( $subdomainId, $uri )
    {
        return $this->findOne( array(
            'subdomainId'   => $subdomainId,
            'uri'           => $uri,
        ) );
    }

    /**
     * Get default by content & subdomain
     *
     * @param   int             $contentId
     * @param   int             $subdomainId
     * @param   string|array    $locales
     * @return  \Grid\Core\Model\Uri\Structure|null
     */
    public function findDefaultByContentSubdomain( $contentId,
                                                   $subdomainId,
                                                   $locales = null )
    {
        $locales = (array) ( $locales ?: Locale::getDefault() );

        foreach ( $locales as $index => $locale )
        {
            if ( is_numeric( $index ) )
            {
                $locales[$locale] = 1.0;
                unset( $locales[$index] );
            }
        }

        foreach ( $locales as $locale => $q )
        {
            $lang = Locale::getPrimaryLanguage( $locale );

            if ( empty( $locales[$lang] ) )
            {
                $locales[$lang] = 3 * $q / 4;
            }
        }

        $exprStr    = '';
        $exprParams = array();
        $exprTypes  = array();
        $platform   = $this->getDbAdapter()
                           ->getPlatform();

        foreach ( $locales as $locale => $q )
        {
            $exprStr .= ' WHEN ? THEN ?';

            $exprParams[] = $locale;
            $exprParams[] = $q;

            $exprTypes[] = Expression::TYPE_VALUE;
            $exprTypes[] = Expression::TYPE_VALUE;
        }

        return $this->findOne( array(
            'contentId'     => $contentId,
            'subdomainId'   => $subdomainId,
            'locale'        => array_keys( $locales ),
        ), array(
            new Expression(
                'CASE ' . $platform->quoteIdentifier( 'locale' ) .
                    $exprStr . ' ELSE 0.0 END DESC',
                $exprParams,
                $exprTypes
            ),
            'locale'    => 'DESC',
            'default'   => 'DESC',
            'id'        => 'ASC',
        ) );
    }

    /**
     * Get default by content & locale
     *
     * @param   int         $contentId
     * @param   string      $locale
     * @param   int|null    $preferredSubdomainId
     * @return  \Grid\Core\Model\Uri\Structure|null
     */
    public function findDefaultByContentLocale( $contentId,
                                                $locale,
                                                $preferredSubdomainId = null )
    {
        $locale = (string) $locale;
        $priLng = Locale::getPrimaryLanguage( $locale );

        if ( $priLng != $locale )
        {
            $locale = array( $locale, $priLng );
        }

        $platform = $this->getDbAdapter()
                         ->getPlatform();

        return $this->findOne( array(
            'contentId'     => $contentId,
            'locale'        => $locale,
        ), array(
            new Expression(
                'CASE ' . $platform->quoteIdentifier( 'subdomainId' ) .
                    ' WHEN ? THEN 1 ELSE 0 END DESC',
                array( (int) $preferredSubdomainId ),
                array( Expression::TYPE_VALUE )
            ),
            'locale'    => 'DESC',
            'default'   => 'DESC',
            'id'        => 'ASC',
        ) );
    }

    /**
     * Return true, if uri is exists in a subdomain
     *
     * @param   int     $subdomainId
     * @param   string  $uri
     * @param   int     $excludeUriId
     * @return  bool
     */
    public function isSubdomainUriExists( $subdomainId, $uri, $excludeUriId = null )
    {
        return $this->isExists( empty( $excludeUriId ) ? array(
            'subdomainId'   => $subdomainId,
            'uri'           => $uri,
        ) : array(
            'subdomainId'   => $subdomainId,
            'uri'           => $uri,
            new Predicate\Operator(
                'id',
                Predicate\Operator::OP_NE,
                $excludeUriId
            ),
        ) );
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
            'subdomain' => array(
                'where'     => static::$tableName . '.subdomainId = subdomain.id',
                'columns'   => array( 'subdomain' ),
                'type'      => Select::JOIN_LEFT,
            ),
            'paragraph_content' => array(
                'table'     => array( 'paragraph_content' => 'paragraph' ),
                'where'     => static::$tableName . '.contentId = paragraph_content.id',
                'columns'   => array( 'contentName' => 'name' ),
                'type'      => Select::JOIN_LEFT,
            ),
        ) );

        return parent::getPaginator( $where, $order, $columns, $joins, $quantifier );
    }

}
