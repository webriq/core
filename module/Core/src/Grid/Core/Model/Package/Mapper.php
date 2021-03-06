<?php

namespace Grid\Core\Model\Package;

use LimitIterator;
use Zend\Json\Json;
use Zend\Paginator;
use Zend\Http\Client as HttpClient;
use Zork\Model\MapperAwareInterface;
use Zend\Stdlib\Hydrator\HydratorInterface;
use Zork\Model\Mapper\ReadOnlyMapperInterface;
use Zork\Model\Mapper\ReadListMapperInterface;
use Zend\ModuleManager\ModuleManagerInterface;
use Grid\User\Model\User\Mapper as UserMapper;

/**
 * Mapper
 *
 * @author David Pozsar <david.pozsar@megaweb.hu>
 */
class Mapper implements HydratorInterface,
                        ReadOnlyMapperInterface,
                        ReadListMapperInterface
{

    /**
     * @const string
     */
    const LOCK_FILE = './composer.lock';

    /**
     * @const string
     */
    const PACKAGE_DATA_URL = 'https://packagist.org/packages/%s.json';

    /**
     * @const string
     */
    const PACKAGE_LIST_URL = 'https://packagist.org/search.json?type=gridguyz&q=%s';

    /**
     * @var \Zend\Http\Client
     */
    protected $httpClient;

    /**
     * @var \Grid\Core\Model\Package\EnabledList
     */
    protected $enabledList;

    /**
     * @var \Zend\ModuleManager\ModuleManagerInterface
     */
    protected $moduleManager;

    /**
     * @var \Grid\User\Model\User\Mapper
     */
    protected $userMapper;

    /**
     * @var array
     */
    protected static $userCache = array();

    /**
     * @var array
     */
    protected static $lockData;

    /**
     * Get http client
     *
     * @param   string|null $uri
     * @return  \Zend\Http\Client
     */
    public function getHttpClient( $uri = null )
    {
        $client = clone $this->httpClient;

        if ( $uri )
        {
            $client->setUri( $uri );
        }

        return $client;
    }

    /**
     * Set http client
     *
     * @param   \Zend\Http\Client   $httpClient
     * @return  \Grid\Core\Model\Module\Mapper
     */
    public function setHttpClient( HttpClient $httpClient )
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * Get enabled-list
     *
     * @return  \Grid\Core\Model\Package\EnabledList
     */
    public function getEnabledList()
    {
        return clone $this->enabledList;
    }

    /**
     * Set enabled-list
     *
     * @param   \Grid\Core\Model\Package\EnabledList    $enabledList
     * @return  \Grid\Core\Model\Module\Mapper
     */
    public function setEnabledList( EnabledList $enabledList )
    {
        $this->enabledList = $enabledList;
        return $this;
    }

    /**
     * @return  \Zend\ModuleManager\ModuleManagerInterface
     */
    public function getModuleManager()
    {
        return $this->moduleManager;
    }

    /**
     * @param   \Zend\ModuleManager\ModuleManagerInterface $moduleManager
     * @return  \Core\View\Helper\AppService
     */
    public function setModuleManager( ModuleManagerInterface $moduleManager )
    {
        $this->moduleManager = $moduleManager;
        return $this;
    }

    /**
     * @return  \Grid\User\Model\User\Mapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param   \Grid\User\Model\User\Mapper    $userMapper
     * @return  \Core\View\Helper\AppService
     */
    public function setUserMapper( UserMapper $userMapper )
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * Construct mapper
     *
     * @param   \Zend\Http\Client                           $httpClient
     * @param   \Grid\Core\Model\Package\EnabledList        $enabledList
     * @param   \Zend\ModuleManager\ModuleManagerInterface  $moduleManager
     * @param   \Grid\User\Model\User\Mapper                $userMapper
     */
    public function __construct( HttpClient             $httpClient,
                                 EnabledList            $enabledList,
                                 ModuleManagerInterface $moduleManager,
                                 UserMapper             $userMapper )
    {
        $this->setHttpClient( $httpClient )
             ->setEnabledList( $enabledList )
             ->setModuleManager( $moduleManager )
             ->setUserMapper( $userMapper );
    }

    /**
     * Get categories
     *
     * @return  array
     */
    public function getCategories()
    {
        return $this->getEnabledList()
                    ->getKeys();
    }

    /**
     * Get enabled pattern count
     *
     * @return  array
     */
    public function getEnabledPackageCount()
    {
        return $this->getEnabledList()
                    ->getPackageCount();
    }

    /**
     * Get user by email
     *
     * @param   string  $email
     * @return  \Grid\User\Model\User\Structure
     */
    public function getUserByEmail( $email )
    {
        if ( ! array_key_exists( $email, static::$userCache ) )
        {
            static::$userCache[$email] = $this->getUserMapper()
                                              ->findByEmail( $email );
        }

        return static::$userCache[$email];
    }

    /**
     * Can modify packages?
     *
     * @return  bool
     */
    public function canModify()
    {
        if ( ! $this->getEnabledList()
                    ->canModify() )
        {
            return false;
        }

        $modules = $this->getModuleManager()
                        ->getModules();

        if ( ! in_array( 'Grid\MultisitePlatform', $modules ) )
        {
            return true;
        }

        return in_array( 'Grid\MultisiteCentral', $modules );
    }

    /**
     * Lazy-load lock data
     *
     * @return  array
     */
    protected static function getLockData()
    {
        if ( null === static::$lockData )
        {
            $data = Json::decode(
                file_get_contents( static::LOCK_FILE ),
                Json::TYPE_ARRAY
            );

            if ( empty( $data ) )
            {
                static::$lockData = array();
            }
            else
            {
                static::$lockData = (array) $data;
            }
        }

        return static::$lockData;
    }

    /**
     * Query JSON data by uri
     *
     * @param   string  $uri
     * @return  mixed
     */
    protected function queryJson( $uri )
    {
        $response = $this->getHttpClient( $uri )
                         ->send();

        if ( $response->isOk() )
        {
            $headers = $response->getHeaders();

            if ( $headers->has( 'Content-Type' ) &&
                 preg_match( '#^(application|text)/(.*\+)?json(\s*;.*)?$#',
                             $headers->get( 'Content-Type' )->getFieldValue() ) )
            {
                return Json::decode( $response->getBody(), Json::TYPE_ARRAY );
            }
        }

        return null;
    }

    /**
     * Crawl package data by name
     *
     * @param   string  $name
     * @return  array
     */
    protected function queryData( $name )
    {
        static $safeUrl = array(
            '%2F' => '/',
            '%2f' => '/',
        );

        static $stabilityWeights = array(
            'stable'    => 4,
            'rc'        => 3,
            'beta'      => 2,
            'alpha'     => 1,
            'dev'       => 0,
        );

        $data = null;
        $lock = static::getLockData();
        $minStability = isset( $lock['minimum-stability'] )
                ? strtolower( $lock['minimum-stability'] )
                : 'stable';

        if ( ! isset( $stabilityWeights[$minStability] ) )
        {
            $minStability = 'stable';
        }

        if ( ! empty( $lock['packages'] ) )
        {
            foreach ( $lock['packages'] as $package )
            {
                if ( isset( $package['name'] ) &&
                     isset( $package['type'] ) &&
                     isset( $package['version'] ) &&
                     strtolower( $package['name'] ) == strtolower( $name ) )
                {
                    $data = array(
                        'name'              => $package['name'],
                        'type'              => $package['type'],
                        'description'       => isset( $package['description'] )
                                             ? $package['description'] : '',
                        'installedVersion'  => $package['version'],
                    );

                    if ( ! empty( $package['source']['reference'] ) )
                    {
                        $data['installedReference'] = $package['source']['reference'];
                    }

                    if ( ! empty( $package['time'] ) )
                    {
                        $data['installedTime'] = $package['time'];
                    }

                    if ( ! empty( $package['license'] ) )
                    {
                        $data['license'] = (array) $package['license'];
                    }

                    if ( ! empty( $package['keywords'] ) )
                    {
                        $data['keywords'] = (array) $package['keywords'];
                    }

                    if ( ! empty( $package['authors'] ) )
                    {
                        $data['authors'] = (array) $package['authors'];
                    }

                    if ( ! empty( $package['homepage'] ) )
                    {
                        $data['homepage'] = $package['homepage'];
                    }

                    if ( ! empty( $package['extra']['display-icon'] ) )
                    {
                        $data['displayIcon'] = $package['extra']['display-icon'];
                    }

                    if ( ! empty( $package['extra']['display-name'] ) )
                    {
                        $data['displayName'] = $package['extra']['display-name'];
                    }

                    if ( ! empty( $package['extra']['display-description'] ) )
                    {
                        $data['displayDescription'] = $package['extra']['display-description'];
                    }

                    if ( ! empty( $package['extra']['module'] ) )
                    {
                        $data['module'] = (string) $package['extra']['module'];
                    }

                    break;
                }
            }
        }

        $package = $this->queryJson( sprintf(
            static::PACKAGE_DATA_URL,
            strtr( urlencode( $name ), $safeUrl )
        ) );

        if ( isset( $package['package'] ) )
        {
            $package = $package['package'];

            if ( null === $data )
            {
                $data = array();
            }

            if ( empty( $data['name'] ) && ! empty( $package['name'] ) )
            {
                $data['name'] = $package['name'];
            }

            if ( empty( $data['type'] ) && ! empty( $package['type'] ) )
            {
                $data['type'] = $package['type'];
            }

            if ( empty( $data['description'] ) && ! empty( $package['description'] ) )
            {
                $data['description'] = $package['description'];
            }

            if ( isset( $package['favers'] ) )
            {
                $data['favourites'] = $package['favers'];
            }

            if ( isset( $package['downloads']['total'] ) )
            {
                $data['downloads'] = $package['downloads']['total'];
            }

            if ( ! empty( $package['time'] ) )
            {
                $data['availableTime'] = $package['time'];
            }

            if ( ! empty( $package['versions'] ) )
            {
                $versionNameMax = array();
                $versionDataMax = array();

                foreach ( $package['versions'] as $versionName => $versionData )
                {
                    switch ( true )
                    {
                        case $versionName == 'dev-master':
                        case (bool) preg_match( '/[\.\-]dev([\d\.\-]|$)/i', $versionName ):
                            $versionStability = 'dev';
                            break;

                        case (bool) preg_match( '/[\.\-]a(lpha)?([\d\.\-]|$)/i', $versionName ):
                            $versionStability = 'aplha';
                            break;

                        case (bool) preg_match( '/[\.\-]b(eta)?([\d\.\-]|$)/i', $versionName ):
                            $versionStability = 'beta';
                            break;

                        case (bool) preg_match( '/[\.\-]rc([\d\.\-]|$)/i', $versionName ):
                            $versionStability = 'rc';
                            break;

                        default:
                            $versionStability = 'stable';
                            break;
                    }

                    if ( $stabilityWeights[$minStability] > $stabilityWeights[$versionStability] )
                    {
                        continue;
                    }

                    if ( empty( $versionNameMax[$versionStability] ) ||
                         $versionName == 'dev-master' ||
                         version_compare( $versionName, $versionNameMax[$versionStability], '>' ) )
                    {
                        $versionNameMax[$versionStability] = $versionName;
                        $versionDataMax[$versionStability] = $versionData;
                    }
                }

                $versionName = null;
                $versionData = null;

                foreach ( $stabilityWeights as $stability => $weight )
                {
                    if ( ! empty( $versionNameMax[$stability] ) &&
                         ! empty( $versionDataMax[$stability] ) )
                    {
                        $versionName = $versionNameMax[$stability];
                        $versionData = $versionDataMax[$stability];
                        break;
                    }
                }

                if ( $versionName && $versionData )
                {
                    $data['availableVersion'] = $versionName;

                    if ( ! empty( $versionData['source']['reference'] ) )
                    {
                        $data['availableReference'] = $versionData['source']['reference'];
                    }

                    if ( ! empty( $versionData['time'] ) )
                    {
                        $data['availableTime'] = $versionData['time'];
                    }

                    if ( empty( $data['license'] ) &&
                         ! empty( $versionData['license'] ) )
                    {
                        $data['license'] = (array) $versionData['license'];
                    }

                    if ( empty( $data['keywords'] ) &&
                         ! empty( $versionData['keywords'] ) )
                    {
                        $data['keywords'] = (array) $versionData['keywords'];
                    }

                    if ( empty( $data['authors'] ) &&
                         ! empty( $versionData['authors'] ) )
                    {
                        $data['authors'] = (array) $versionData['authors'];
                    }

                    if ( empty( $data['homepage'] ) &&
                         ! empty( $versionData['homepage'] ) )
                    {
                        $data['homepage'] = $versionData['homepage'];
                    }

                    if ( empty( $data['displayIcon'] ) &&
                         ! empty( $versionData['extra']['display-icon'] ) )
                    {
                        $data['displayIcon'] = $versionData['extra']['display-icon'];
                    }

                    if ( empty( $data['displayName'] ) &&
                         ! empty( $versionData['extra']['display-name'] ) )
                    {
                        $data['displayName'] = $versionData['extra']['display-name'];
                    }

                    if ( empty( $data['displayDescription'] ) &&
                         ! empty( $versionData['extra']['display-description'] ) )
                    {
                        $data['displayDescription'] = $versionData['extra']['display-description'];
                    }

                    if ( empty( $data['module'] ) &&
                         ! empty( $versionData['extra']['module'] ) )
                    {
                        $data['module'] = (string) $versionData['extra']['module'];
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Query by contains some words
     *
     * @param   string  $query
     * @param   string  $name
     * @param   string  $description
     * @param   array   $keywords
     * @return  bool
     */
    private function contains( $query, $name, $description, array $keywords )
    {
        $endResult      = true;
        $query          = mb_strtolower( $query );
        $name           = mb_strtolower( $name );
        $description    = mb_strtolower( $description );

        foreach ( $keywords as & $keyword )
        {
            $keyword = mb_strtolower( $keyword );
        }

        foreach ( preg_split( '/[\b\s]+/', $query ) as $word )
        {
            if ( empty( $word ) )
            {
                continue;
            }

            $endResult = false;

            if ( false !== strpos( $name, $word ) ||
                 false !== strpos( $description, $word ) )
            {
                return true;
            }

            foreach ( $keywords as $keyword )
            {
                if ( false !== strpos( $keyword, $word ) )
                {
                    return true;
                }
            }
        }

        return $endResult;
    }

    /**
     * Query available & installed package names
     *
     * @param   array|string|null   $where category, installed, contains, author
     * @return  array
     */
    protected function queryNames( $where = null )
    {
        $result     = array();
        $lock       = static::getLockData();
        $enabled    = $this->getEnabledList();

        if ( $where )
        {
            if ( is_scalar( $where ) )
            {
                $where = array(
                    'category' => (string) $where,
                );
            }
            else
            {
                $where = (array) $where;
            }
        }
        else
        {
            $where = array();
        }

        if ( empty( $where['category'] ) )
        {
            $where['category'] = null;
        }
        else
        {
            $where['category'] = (array) $where['category'];
        }

        if ( empty( $where['contains'] ) )
        {
            $where['contains'] = null;
        }

        if ( ! isset( $where['installed'] ) || '' === $where['installed'] )
        {
            $where['installed'] = null;
        }
        else
        {
            $where['installed'] = (bool) $where['installed'];
        }

        if ( ! isset( $where['author'] ) || '' === $where['author'] )
        {
            $where['author'] = null;
        }
        else
        {
            $where['author'] = (string) $where['author'];
        }

        if ( null === $where['author'] &&
             ( ! isset( $where['installed'] ) || ! $where['installed'] ) )
        {
            $total    = 0;
            $packages = $this->queryJson( sprintf(
                static::PACKAGE_LIST_URL,
                urlencode( $where['contains'] )
            ) );

            while ( ! empty( $packages['results'] ) )
            {
                foreach ( $packages['results'] as $package )
                {
                    if ( $enabled->isEnabled( $package['name'],
                                              $where['category'] ) )
                    {
                        $result[$package['name']] = $package['name'];
                    }

                    $total++;
                }

                if ( isset( $packages['total'] ) &&
                     $total >= $packages['total'] )
                {
                    break;
                }

                if ( empty( $packages['next'] ) )
                {
                    $packages = array();
                }
                else
                {
                    $packages = $this->queryJson( $packages['next'] );
                }
            }
        }

        if ( ! empty( $lock['packages'] ) )
        {
            foreach ( $lock['packages'] as $package )
            {
                if ( isset( $package['name'] ) &&
                     isset( $package['type'] ) &&
                     preg_match( Structure::VALID_TYPES, $package['type'] ) &&
                     $enabled->isEnabled( $package['name'], $where['category'] ) && (
                         ! $where['contains'] || $this->contains(
                             $where['contains'],
                             $package['name'],
                             isset( $package['description'] ) ? $package['description'] : null,
                             isset( $package['keywords'] ) ? (array) $package['keywords'] : array()
                         )
                     ) )
                {
                    if ( ! isset( $where['installed'] ) || $where['installed'] )
                    {
                        if ( null === $where['author'] )
                        {
                            $result[$package['name']] = $package['name'];
                        }
                        else if ( empty( $package['authors'] ) )
                        {
                            unset( $result[$package['name']] );
                        }
                        else
                        {
                            foreach ( (array) $package['authors'] as $author )
                            {
                                if ( ! empty( $author['email'] ) && $author['email'] == $where['author'] )
                                {
                                    $result[$package['name']] = $package['name'];
                                    continue 2;
                                }
                            }

                            unset( $result[$package['name']] );
                        }
                    }
                    else if ( isset( $result[$package['name']] ) )
                    {
                        unset( $result[$package['name']] );
                    }
                }
            }
        }

        return array_values( $result );
    }

    /**
     * Find a structure by name
     *
     * @param   string  $name
     * @return  \Grid\Core\Model\Package\Structure
     */
    public function find( $name )
    {
        if ( ! $this->getEnabledList()
                    ->isEnabled( $name ) )
        {
            return null;
        }

        $data = $this->queryData( $name );

        if ( $data )
        {
            $structure = new Structure( $data );
            $structure->setMapper( $this );
            return $structure;
        }

        return null;
    }

    /**
     * Find iterator
     *
     * @param   array|string|null   $where category, installed, contains, author
     * @param   bool|null           $order
     * @return  \Iterator
     */
    protected function findIterator( $where = null, $order = null )
    {
        $names = $this->queryNames( $where );

        if ( null !== $order && '' !== $order )
        {
            sort( $names, SORT_NATURAL );

            if ( ! $order )
            {
                $names = array_reverse( $names );
            }
        }

        return new StructureList( $this, $names );
    }

    /**
     * Find multiple structures
     *
     * @param   mixed|null  $where category, installed, contains, author
     * @param   mixed|null  $order
     * @param   int|null    $limit
     * @param   int|null    $offset
     * @return  \Grid\Core\Model\Package\Structure[]
     */
    public function findAll( $where     = null,
                             $order     = null,
                             $limit     = null,
                             $offset    = null )
    {
        $iterator = $this->findIterator( $where, $order );

        if ( $limit || $offset )
        {
            $iterator = new LimitIterator(
                $iterator,
                (int) $offset,
                (int) $limit ?: null
            );
        }

        return $iterator;
    }

    /**
     * Find one structure
     *
     * @param   mixed|null  $where category, installed, contains, author
     * @param   mixed|null  $order
     * @return  \Grid\Core\Model\Package\Structure
     */
    public function findOne( $where = null, $order = null )
    {
        foreach ( $this->findIterator( $where, $order ) as $structure )
        {
            if ( $structure )
            {
                return $structure;
            }
        }

        return null;
    }

    /**
     * Get paginator
     *
     * @param   mixed|null  $where category, installed, contains, author
     * @param   mixed|null  $order
     * @return  \Zend\Paginator\Paginator
     */
    public function getPaginator( $where = null, $order = null )
    {
        return new Paginator\Paginator(
            new Paginator\Adapter\Iterator(
                $this->findIterator( $where, $order )
            )
        );
    }

    /**
     * Extract values from a structure
     *
     * @param object $structure
     * @return array
     */
    public function extract( $structure )
    {
        if ( $structure instanceof Structure )
        {
            return $structure->toArray();
        }

        return (array) $structure;
    }

    /**
     * Hydrate $object with the provided $data.
     *
     * @param   array   $data
     * @param   object  $structure
     * @return  object
     */
    public function hydrate( array $data, $structure )
    {
        if ( $structure instanceof Structure )
        {
            $structure->setOptions( $data );
        }
        else
        {
            foreach ( $data as $key => $value )
            {
                $structure->$key = $value;
            }
        }

        if ( $structure instanceof MapperAwareInterface )
        {
            $structure->setMapper( $this );
        }

        return $structure;
    }

}
