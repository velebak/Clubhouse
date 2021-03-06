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
 * Sending and getting messages.
 * Access: restricted to signed-in users through AJAX requests
 *
 * @copyright Copyright (c) 2010 Radek Stepan
 * @package   Clubhouse\Presenters
 */
final class MessagePresenter extends Fari_ApplicationPresenter {

    private $user = FALSE;
    private $room;

    /**
     * Applied automatically before any action is called.
     */
	public function filterStartup() {
        // is this Ajax?
        if ($this->request->isAjax()) {
            // is user authenticated?
            try {
                $this->user = new User();
                $this->user->canEnter($roomId);
                
            } catch (UserNotAuthenticatedException $e) {
                // user is fetching new messages... not for long
                $this->renderJson('bye');

            } catch (UserNotAuthorizedException $e) {
                $this->renderJson('bye');
                
            }

            $this->room = new Room();
        } else {
            $this->renderTemplate('error404/javascript');
        }
	}
	
	public function actionIndex($p) { $this->redirectTo('/error404/'); }



    /********************* action send a message *********************/



    /**
	 * Send a message from a room
     *
     * @uses Ajax
	 */
    public function actionSpeak($roomId) {
        $text = Fari_Escape::text(Fari_Decode::javascript($this->request->getRawPost('text')));
        if (!empty($text)) {
            $time = mktime();

            // a text message
            $message = new MessageSpeak($roomId, $time);
            $message->text($roomId, $time, $this->user->getShortName(), $this->user->getId(), $text);

            // the message might be saved under wrong room id, but activity updater will kick us...
            try {
                $this->room->updateUserActivity($roomId, $time, $this->user->getId());
            } catch (UserNotFoundException $e) {
                $this->renderJson('bye');
            }
        }
    }



    /********************* action retrieve messages *********************/



    /**
	 * Retrieve last messages from a room
     *
     * @uses Ajax
	 */
    public function actionGet($roomId, $lastMessage) {
        if (Fari_Filter::isInt($roomId) && Fari_Filter::isInt($lastMessage)) {
            $time = mktime();

            $messages = new Message();
            $messages = $messages->getLatest($lastMessage, $roomId);

            $system = new System();

            try {
                $this->room->updateUserActivity($roomId, $time, $this->user->getId());
            } catch (UserNotFoundException $e) {
                $this->renderJson('bye');
            }

            $this->renderJson($messages);
        } else $this->renderJson('bye');
    }



    /********************* action set highlight status *********************/



    /**
	 * Message highlighting
     *
     * @uses Ajax
	 */
    public function actionHighlight($messageId) {
        if (Fari_Filter::isInt($messageId)) {
            $time = mktime();

            $messages = new Message();

            try {
                $result = $messages->switchHighlight($messageId);
            } catch (MessageNotFoundException $e) {
                // you mess with us... we mess with you
                $this->renderJson('bye');
            }

            $this->renderJson($result);
        } else $this->renderJson('bye');
    }

}