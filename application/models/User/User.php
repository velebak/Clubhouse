<?php if (!defined('FARI')) die();

/**
 * Clubhouse, a 37Signals' Campfire port
 *
 * @copyright Copyright (c) 2010 Radek Stepan
 * @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link      http://radekstepan.com
 * @category  Clubhouse
 */



/**
 * User authentication & permissions checking, identity....
 *
 * @copyright Copyright (c) 2010 Radek Stepan
 * @package   Clubhouse\Models\User
 */
class User extends Fari_AuthenticatorSimple {

    private $db;

    private $identity;

    private $permissions = array();
    private $inRoom = array();

    /**
     * Create object for authenticated user
     */
    function __construct($roles=NULL) {
        $this->db = Fari_Db::getConnection();
        parent::__construct();

        // no entry, we are not logged in, fail the constructor
        if (!$this->isAuthenticated()) throw new UserNotAuthenticatedException();

        // fetch the database entry for us
        $dbUser = $this->db->selectRow('users', 'id, role, name, surname, short, long, invitation',
            array('username' => $this->getCredentials()));
        
        // user has been inactivated, throw them away
        if ($dbUser['role'] == 'inactive') throw new UserNotAuthenticatedException();
        
        // ORM much? effectively map db entry into an identity Fari_Bag object
        $this->identity = new Fari_Bag();
        foreach ($dbUser as $key => $value) $this->identity->$key = $value;
        
        // get an array of room permissions for us
        $q = $this->db->select(
            'user_permissions', 'room', array('user' => $dbUser['id']), 'room ASC');
        foreach($q as $room) array_push($this->permissions, $room['room']);

        // which rooms are we in?
        $q = $this->db->select(
            'room_users JOIN rooms ON room_users.room=rooms.id',
            'rooms.id, name',
            array('user' => $dbUser['id']), 'room ASC');
        foreach($q as $room) $this->inRoom[$room['name']] = $room['id'];

        // optionally check the roles
        if (isset($roles)) {
            if (!$this->isAuthorized(&$roles, $dbUser['role'])) throw new UserNotAuthorizedException();
        }
    }

    private function isAuthorized($roles, $ourRole) {
        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($ourRole === $role) {
                    return TRUE;
                }
            }
        } else {
            if ($ourRole === $roles) return TRUE;
        }
        
        return FALSE;
    }

    function getId() {
        return $this->identity->id;
    }

    /**
     * Get admin details
     */
    function getAdmin() {
        return $this->db->selectRow('users', 'id, long', array('role' => 'admin'));
    }

    function getShortName() {
        return $this->identity->short;
    }



    /********************* roles *********************/



    function role() {
        return $this->identity->role;
    }
    function isAdmin() {
        return ($this->identity->role == 'admin') ? TRUE : FALSE;
    }
    function isGuest() {
        return ($this->identity->role == 'guest') ? TRUE : FALSE;
    }



    /********************* permissions *********************/



    /**
     * Return a formatted string of rooms we have permissions for
     * @return string E.g.: 1,2,6
     */
    function getPermissionsDbString() {
        $result = '';
        foreach ($this->permissions as $room) $result .= $room . ',';
        return (!empty($result)) ? substr($result, 0, -1) : $result;
    }

	/**
	 * Can the user enter/speak in a room?
     *
     * @param integer $roomId An integer with room identifier (assertion!)
     * @return boolean TRUE if we can enter the room otherwise throws UserNotAuthorizedException
	 */
    function canEnter($roomId) {
        // admin has access everywhere
        if ($this->isAdmin()) return TRUE;
        
        // fetch the room
        $room = $this->db->selectRow('rooms', 'locked, guest', array('id' => $roomId));
        // we might not have permissions but the room might be guest open and we are guest?
        if ($room['guest'] != '0' && $this->isGuest()) return TRUE;

        // locked for guests and we don't have permissions... we definitely can't enter
        if (!$this->havePermissions($roomId)) throw new UserNotAuthorizedException();

        // we might have permissions but room might be locked so check if we are in already
        if ($this->inRoom($roomId)) return TRUE;

        // fail the room is locked...
        if ($room['locked'] == '1') {
            throw new UserNotAuthorizedException();
        } else {
            TRUE;
        }
    }

    /**
     * Do we have permissions for a given room?
     */
    function havePermissions($roomId) {
        return (in_array($roomId, $this->permissions)) ? TRUE : FALSE;
    }



    /********************* room presence *********************/



    /**
     * Is the user in a given room?
     */
    function inRoom($roomId) {
        return (in_array($roomId, $this->inRoom)) ? TRUE : FALSE;
    }

    function inRooms() {
        return $this->inRoom;
    }

    /**
     * Enter user into the room
     *
     * @param integer $roomId
     * @param integer $time
     * @return void
     */
    function enterRoom($roomId, $time=null) {
        if (!isset($time)) $time = mktime();
        // insert into the database
        $this->db->insert('room_users', array('room' => $roomId, 'user' => $this->identity->id, 'timestamp' => $time));

        // update our object
        $room = $this->db->selectRow('rooms', 'name', array('id' => $roomId));
        $this->inRoom[$room['name']] = $roomId;
    }

    /**
     * Leave room
     *
     * @param integer $roomId
     * @param integer $time
     * @return void
     */
    function leaveRoom($roomId) {
        $this->db->delete('room_users', array('user' => $this->identity->id, 'room' => $roomId));
        foreach ($this->inRoom as $name => $id) {
            // unset from our object
            if ($id == $roomId) unset($this->inRoom[$name]);
        }
    }

}