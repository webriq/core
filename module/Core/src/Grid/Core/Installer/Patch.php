<?php

namespace Grid\Core\Installer;

use Grid\Installer\Exception;
use Grid\Installer\AbstractPatch;

/**
 * Patch
 *
 * @author David Pozsar <david.pozsar@megaweb.hu>
 *
 * @method \PDO getDb()
 */
class Patch extends AbstractPatch
{

    /**
     * @const int
     */
    const DEVELOPER_GROUP = 1;

    /**
     * @const int
     */
    const SITE_OWNER_GROUP = 2;

    /**
     * Uploads dirs to generate upon installation
     *
     * @var array
     */
    protected $uploadsDirs = array(
        'pages',
        'pages/images',
        'pages/documents',
        'settings',
        'customize',
        'users',
    );

    /**
     * Uploads files to copy
     *
     * @var array
     */
    protected $uploadsFiles = array();

    /**
     * Run after patching
     *
     * @param   string  $from
     * @param   string  $to
     * @return  void
     */
    public function afterPatch( $from, $to )
    {
        $schema = $this->getPatchData()
                       ->get( 'db', 'schema' );

        if ( is_array( $schema ) )
        {
            $schema = reset( $schema );
        }

        if ( $this->isZeroVersion( $from ) )
        {
            $developer = $this->selectFromTable( 'user', 'id', array(
                'groupId' => static::DEVELOPER_GROUP,
            ) );

            if ( ! $developer )
            {
                $developer = $this->insertDeveloper();
            }

            $platformOwner = $this->selectFromTable( 'user', 'id', array(
                'groupId' => static::SITE_OWNER_GROUP,
            ) );

            if ( ! $platformOwner )
            {
                $platformOwner = $this->insertPlatformOwner();
            }

            $content = $this->selectFromTable( 'paragraph', 'id', array(
                'type' => 'content',
            ) );

            if ( ! $content )
            {
                $content = $this->insertDefaultParagraph( 'content' );
            }

            $menu = $this->selectFromTable( 'menu', 'id' );

            if ( ! $menu )
            {
                $menu = $this->insertDefaultMenu( $content );
            }

            $layout = $this->selectFromTable( 'paragraph', 'id', array(
                'type' => 'layout',
            ) );

            if ( ! $layout )
            {
                $layout = $this->insertDefaultParagraph( 'layout' );
            }

            $subDomain = $this->selectFromTable( 'subdomain', 'id', array(
                'subdomain' => '',
            ) );

            if ( ! $subDomain )
            {
                $subDomain = $this->insertDefaultSubDomain( $layout, $content );
            }

            if ( ! empty( $schema ) )
            {
                foreach ( $this->uploadsDirs as $uploadsDir )
                {
                    @ mkdir(
                        implode( DIRECTORY_SEPARATOR, array(
                            '.',
                            'public',
                            'uploads',
                            $schema,
                            str_replace( '/', DIRECTORY_SEPARATOR, $uploadsDir )
                        ) ),
                        0777,
                        true
                    );
                }

                foreach ( $this->uploadsFiles as $uploadsFile => $copyFrom )
                {
                    @ copy(
                        str_replace( '/', DIRECTORY_SEPARATOR, $copyFrom ),
                        implode( DIRECTORY_SEPARATOR, array(
                            '.',
                            'public',
                            'uploads',
                            $schema,
                            str_replace( '/', DIRECTORY_SEPARATOR, $uploadsFile )
                        ) )
                    );
                }
            }
        }
        else
        {
            $schemas = array( $schema );

            if ( $this->getPatcher()
                      ->isMultisite() )
            {
                $schemas = $this->selectColumnFromTable(
                    array( '_central', 'site' ),
                    'schema'
                );
            }

            foreach ( $schemas as $schema )
            {
                $extraCssFile = 'public/uploads/' . $schema . '/customize/extra.css';

                if ( file_exists( $extraCssFile ) && is_readable( $extraCssFile ) )
                {
                    $extraCss = preg_replace(
                        '/^\s*@charset\s+(["\'][^"\']+["\']|[^;]+)\s*;\s+/',
                        '',
                        @ file_get_contents( $extraCssFile )
                    );

                    if ( ! empty( $extraCss ) )
                    {
                        $this->appendCustomizeGlobalExtra( $extraCss, $schema );
                        $this->getInstaller()
                             ->patchLog( 'customize extra %s merged', $extraCssFile );
                    }

                    @ unlink( $extraCssFile );
                }
            }
        }

        $this->mergePackagesConfig();
    }

    /**
     * Insert developer user
     *
     * @return  int|null
     */
    protected function insertDeveloper()
    {
        $data   = $this->getPatchData();
        $create = $data->get(
            'gridguyz-core',
            'developer',
            'Do you want to create a developer user? (y/n)',
            'n',
            array( 'y', 'n', 'yes', 'no', 't', 'f', 'true', 'false', '1', '0' )
        );

        if ( in_array( strtolower( $create ),
             array( 'n', 'no', 'f', 'false', '0', '' ) ) )
        {
            return null;
        }

        $email = $data->get(
            'gridguyz-core',
            'developer-email',
            'Type the developer\'s email (must be valid email)',
            null,
            '/^[A-Z0-9\._%\+-]+@[A-Z0-9\.-]+\.[A-Z]{2,4}$/i',
            3
        );

        $displayName = $data->get(
            'gridguyz-core',
            'developer-displayName',
            'Type the developer\'s display name',
            strstr( $email, '@', true )
        );

        $password = $data->get(
            'gridguyz-core',
            'developer-password',
            'Type the developer\'s password',
            $this->createPasswordSalt( 6 ),
            true
        );

        return $this->insertIntoTable(
            'user',
            array(
                'email'         => $email,
                'displayName'   => $displayName,
                'passwordHash'  => $this->createPasswordHash( $password ),
                'groupId'       => static::DEVELOPER_GROUP,
                'state'         => 'active',
                'confirmed'     => 't',
                'locale'        => 'en',
            ),
            true
        );
    }

    /**
     * Insert platform-owner user
     *
     * @return  int
     */
    protected function insertPlatformOwner()
    {
        $data  = $this->getPatchData();
        $email = $data->get(
            'gridguyz-core',
            'platformOwner-email',
            'Type the platform owner\'s email (must be valid email)',
            null,
            '/^[A-Z0-9\._%\+-]+@[A-Z0-9\.-]+\.[A-Z]{2,4}$/i',
            3
        );

        $displayName = $data->get(
            'gridguyz-core',
            'platformOwner-displayName',
            'Type the platform owner\'s display name',
            strstr( $email, '@', true )
        );

        $password = $data->get(
            'gridguyz-core',
            'platformOwner-password',
            'Type the platform owner\'s password',
            $this->createPasswordSalt( 6 ),
            true
        );

        return $this->insertIntoTable(
            'user',
            array(
                'email'         => $email,
                'displayName'   => $displayName,
                'passwordHash'  => $this->createPasswordHash( $password ),
                'groupId'       => static::SITE_OWNER_GROUP,
                'state'         => 'active',
                'confirmed'     => 't',
                'locale'        => 'en',
            ),
            true
        );
    }

    /**
     * Create password hash
     *
     * @param   string   $password
     * @return  string
     */
    protected function createPasswordHash( $password )
    {
        if ( function_exists( 'password_hash' ) )
        {
            return password_hash( $password, PASSWORD_DEFAULT );
        }

        if ( ! defined( 'CRYPT_BLOWFISH' ) )
        {
            throw new Exception\RuntimeException( sprintf(
                '%s: CRYPT_BLOWFISH algorithm must be enabled',
                __METHOD__
            ) );
        }

        return crypt(
            $password,
            ( version_compare( PHP_VERSION, '5.3.7' ) >= 0 ? '$2y' : '$2a' ) .
            '$10$' . $this->createPasswordSalt() . '$'
        );
    }

    /**
     * Create password-salt
     *
     * @param   int     $length
     * @return  string
     */
    private function createPasswordSalt( $length = 22 )
    {
        static $chars = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

        if ( function_exists( 'openssl_random_pseudo_bytes' ) &&
             ( version_compare( PHP_VERSION, '5.3.4' ) >= 0 ||
               strtoupper( substr( PHP_OS, 0, 3 ) ) !== 'WIN' ) )
        {
            $bytes = openssl_random_pseudo_bytes( $length, $usable );

            if ( true !== $usable )
            {
                $bytes = null;
            }
        }

        if ( empty( $bytes ) &&
             function_exists( 'mcrypt_create_iv' ) &&
             ( version_compare( PHP_VERSION, '5.3.7' ) >= 0 ||
               strtoupper( substr( PHP_OS, 0, 3 ) ) !== 'WIN' ) )
        {
            $bytes = mcrypt_create_iv( $length, MCRYPT_DEV_URANDOM );

            if ( empty( $bytes ) || strlen( $bytes ) < $length )
            {
                $bytes = null;
            }
        }

        if ( empty( $bytes ) )
        {
            $bytes = '';

            for ( $i = 0; $i < $length; ++$i )
            {
                $bytes .= chr( mt_rand( 0, 255 ) );
            }
        }

        $pos  = 0;
        $salt = '';
        $clen = strlen( $chars );

        for ( $i = 0; $i < $length; ++$i )
        {
            $pos = ( $pos + ord( $bytes[$i] ) ) % $clen;
            $salt .= $chars[$pos];
        }

        return $salt;
    }

    /**
     * Insert default paragraph: content / layout
     *
     * @return  int
     */
    protected function insertDefaultParagraph( $type )
    {
        $data = $this->getPatchData();
        $key  = $type . 'Id';

        if ( $data->has( 'gridguyz-core', $key ) )
        {
            $id = $data->get( 'gridguyz-core', $key );
        }
        else
        {
            $choices    = array();
            $labelToId  = array();
            $first      = null;
            $nextLabel  = 'a';
            $rows       = $this->selectRowsFromTable(
                array( '_central', 'paragraph' ),
                array( 'id', 'name' ),
                array( 'type' => $type ),
                array( 'id'   => 'ASC' )
            );

            foreach ( $rows as $row )
            {
                if ( empty( $first ) )
                {
                    $first = $nextLabel; // $row->id;
                }

                $labelToId[$nextLabel] = $row->id;
                $choices[$nextLabel++] = $row->name . ' (#' . $row->id . ')';
            }

            $data->printChoices( "Available {$type}s:", $choices );

            $id = $data->get(
                'gridguyz-core',
                $key,
                "Type the default $type's id",
                $first,
                array_merge(
                    array_keys( $labelToId ),
                    array_values( $labelToId )
                )
            );

            if ( isset( $labelToId[$id] ) )
            {
                $id = $labelToId[$id];
            }
        }

        $query = $this->query(
            'SELECT "paragraph_clone"( :schema, :id ) AS "result"',
            array(
                'schema'    => '_central',
                'id'        => (int) $id,
            )
        );

        while ( $row = $query->fetchObject() )
        {
            return $row->result;
        }

        return null;
    }

    /**
     * Insert default menu
     *
     * @param   int $content
     * @return  int
     */
    protected function insertDefaultMenu( $content )
    {
        $root = $this->insertIntoTable(
            'menu',
            array(
                'type'  => 'container',
                'left'  => 1,
                'right' => 4,
            ),
            true
        );

        $this->insertIntoTable(
            'menu_label',
            array(
                'menuId'    => $root,
                'locale'    => 'en',
                'label'     => 'Default menu',
            )
        );

        $menuContent = $this->insertIntoTable(
            'menu',
            array(
                'type'  => 'content',
                'left'  => 2,
                'right' => 3,
            ),
            true
        );

        $this->insertIntoTable(
            'menu_label',
            array(
                'menuId'    => $menuContent,
                'locale'    => 'en',
                'label'     => 'Home',
            )
        );

        $this->insertIntoTable(
            'menu_property',
            array(
                'menuId'    => $menuContent,
                'name'      => 'contentId',
                'value'     => $content,
            )
        );

        return $root;
    }

    /**
     * Insert default sub-domain
     *
     * @param   int $layout
     * @param   int $content
     * @return  int
     */
    protected function insertDefaultSubDomain( $layout, $content )
    {
        return $this->insertIntoTable(
            'subdomain',
            array(
                'subdomain'         => '',
                'locale'            => 'en',
                'defaultLayoutId'   => $layout,
                'defaultContentId'  => $content,
            )
        );
    }

    /**
     * Append global extra css to customize
     *
     * @param   string      $globalExtraCss
     * @param   string|null $schema
     * @return  bool
     */
    protected function appendCustomizeGlobalExtra( $globalExtraCss, $schema = null )
    {
        $where = array( 'rootParagraphId' => null );
        $table = 'customize_extra';

        if ( $schema )
        {
            $table = array( $schema, $table );
        }

        $current = $this->selectFromTable(
            $table,
            'extra',
            $where
        );

        if ( null === $current )
        {
            $update = $this->updateTable(
                $table,
                array(
                    'extra' => $globalExtraCss,
                ),
                $where
            );

            if ( ! $update )
            {
                return (bool) $this->insertIntoTable(
                    $table,
                    array(
                        'rootParagraphId'   => null,
                        'extra'             => $globalExtraCss,
                    ),
                    true
                );
            }

            return (bool) $update;
        }

        return (bool) $this->updateTable(
            $table,
            array(
                'extra' => $current . "\n\n" . $globalExtraCss,
            ),
            $where
        );
    }

    /**
     * Merge packages config
     *
     * @return  void
     */
    protected function mergePackagesConfig()
    {
        $this->getInstaller()
             ->mergeConfigData(
                 'packages.local',
                 include __DIR__ . '/../../../../config/default.packages.php',
                 function ( & $config )
                 {
                     if ( ! empty( $config['modules']['Grid\Core']['enabledPackages'] ) )
                     {
                         foreach ( $config['modules']['Grid\Core']['enabledPackages'] as & $packages )
                         {
                             if ( ! is_array( $packages ) )
                             {
                                 $packages = array(
                                     $packages => (string) $packages
                                 );
                             }

                             $packages = array_unique( array_map(
                                 'strtolower',
                                 array_filter(
                                     $packages,
                                     function ( $package )
                                     {
                                         return (bool) preg_match(
                                             '#^[a-z0-9_-]+/[a-z0-9_-]+$#i',
                                             $package
                                         );
                                     }
                                 )
                             ) );
                         }
                     }

                     return $config;
                 }
             );
    }

}
