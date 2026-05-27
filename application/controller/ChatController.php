<?php

/**
 * This controller shows an area that's only visible for logged in users (because of Auth::checkAuthentication(); in line 16)
 */
class ChatController extends Controller
{
    /**
     * Construct this object by extending the basic Controller class
     */
    public function __construct()
    {
        parent::__construct();

        // this entire controller should only be visible/usable by logged in users, so we put authentication-check here
        Auth::checkAuthentication();
    }

    /**
     * This method controls what happens when you move to /chat/index in your app.
     * Shows a list of all users the logged-in user can chat with.
     */
    public function index()
    {
        $this->View->render('chat/index', array(
            'users' => UserModel::getPublicProfilesOfAllUsersExceptCurrentUser(),
            'unread_counts' => ChatModel::getUnreadCountsPerUser()
        ));
    }

    /**
     * Show the chat conversation with a specific user.
     * @param int $receiver_id the user_id of the other user
     */
    public function showChat($receiver_id)
    {
        ChatModel::markAsRead($receiver_id);
        $this->View->render('chat/showChat', array(
            'messages' => ChatModel::getMessages($receiver_id),
            'receiver' => UserModel::getPublicProfileOfUser($receiver_id)
        ));
    }

    /**
     * Handle sending a message to a specific user.
     * @param int $receiver_id the user_id of the recipient
     */
    public function send($receiver_id)
    {
        ChatModel::sendMessage($receiver_id, Request::post('message_text'));
        Redirect::to('chat/showChat/' . (int)$receiver_id);
    }
}
