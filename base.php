<?php

namespace OCA\rest_auth_app;

use OC_DB;

/**
 * Base class for external auth implementations that stores users
 * on their first login in a local table.
 * This is required for making many of the user-related ownCloud functions
 * work, including sharing files with them.
 *
 * Note this code was inspired by https://github.com/owncloud/apps/blob/v9.0.8/user_external/lib/base.php
 */
abstract class Base extends \OC_User_Backend
{

    /**
     * Delete a user
     *
     * @param string $uid The username of the user to delete
     *
     * @return bool
     */
    public function deleteUser($uid)
    {
        OC_DB::executeAudited(
            'DELETE FROM `*PREFIX*users_rest_api_auth` WHERE `uid` = ?',
            [$uid]
        );

        return true;
    }

    /**
     * Get display name of the user
     *
     * @param string $uid user ID of the user
     *
     * @return string display name
     */
    public function getDisplayName($uid)
    {
        \OCP\Util::writeLog('OC_rest_auth_app', "Getting display name for " . $uid, \OCP\Util::DEBUG);

        $user = OC_DB::executeAudited(
            'SELECT `displayname` FROM `*PREFIX*users_rest_api_auth`'
            . ' WHERE `uid` = ?',
            [$uid]
        )->fetchRow();

        \OCP\Util::writeLog('OC_rest_auth_app', "user: " . json_encode($user), \OCP\Util::DEBUG);

        $displayName = trim($user['displayname'], ' ');

        if (!empty($displayName)) {
            return $displayName;
        } else {
            return $uid;
        }
    }

    /**
     * Get a list of all display names and user ids.
     *
     * @return array with all displayNames (value) and the corresponding uids (key)
     */
    public function getDisplayNames($search = '', $limit = null, $offset = null)
    {
        \OCP\Util::writeLog('OC_rest_auth_app', "Getting display names", \OCP\Util::DEBUG);

        $result = OC_DB::executeAudited(
            [
                'sql'    => 'SELECT `uid`, `displayname` FROM `*PREFIX*users_rest_api_auth`'
                    . ' WHERE (LOWER(`displayname`) LIKE LOWER(?) '
                    . ' OR LOWER(`uid`) LIKE LOWER(?))',
                'limit'  => $limit,
                'offset' => $offset
            ],
            [
                '%' . $search . '%',
                '%' . $search . '%'
            ]
        );

        $displayNames = [];
        while ($row = $result->fetchRow()) {
            $displayNames[$row['uid']] = $row['displayname'];
        }

        return $displayNames;
    }

    /**
     * Get a list of all users
     *
     * @return array with all uids
     */
    public function getUsers($search = '', $limit = null, $offset = null)
    {
        $result = OC_DB::executeAudited(
            [
                'sql'    => 'SELECT `uid` FROM `*PREFIX*users_rest_api_auth`'
                    . ' WHERE LOWER(`uid`) LIKE LOWER(?)',
                'limit'  => $limit,
                'offset' => $offset
            ],
            [$search . '%']
        );
        $users = [];
        while ($row = $result->fetchRow()) {
            $users[] = $row['uid'];
        }

        return $users;
    }

    /**
     * Determines if the backend can enlist users
     *
     * @return bool
     */
    public function hasUserListings()
    {
        return true;
    }

    /**
     * Change the display name of a user
     *
     * @param string $uid The username
     * @param string $displayName The new display name
     *
     * @return true/false
     */
    public function setDisplayName($uid, $displayName)
    {
        if (!$this->userExists($uid)) {
            return false;
        }
        OC_DB::executeAudited(
            'UPDATE `*PREFIX*users_rest_api_auth` SET `displayname` = ?'
            . ' WHERE `uid` = ?',
            [
                $displayName,
                $uid
            ]
        );

        return true;
    }

    /**
     * Create user record in database
     *
     * @param string $uid The username
     *
     * @return void
     */
    protected function storeUser($uid)
    {
        if (!$this->userExists($uid)) {
            OC_DB::executeAudited(
                'INSERT INTO `*PREFIX*users_rest_api_auth` ( `uid` )'
                . ' VALUES( ? )',
                [$uid]
            );
        }
    }

    /**
     * Check if a user exists
     *
     * @param string $uid the username
     *
     * @return boolean
     */
    public function userExists($uid)
    {
        $result = OC_DB::executeAudited(
            'SELECT COUNT(*) FROM `*PREFIX*users_rest_api_auth`'
            . ' WHERE LOWER(`uid`) = LOWER(?)',
            [$uid]
        );

        return $result->fetchOne() > 0;
    }
}
