<?php

namespace BearAddon\PinnedProfilePosts\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Member extends XFCP_Member
{
    /*
     * Rewrite of original actionView, as I need to modify how the profile posts are selected. Most of the code is
     * a direct copy and paste from that function.
     * */
    public function actionView(ParameterBag $params)
    {
        if (parent::filter('tooltip', 'bool'))
        {
            return parent::rerouteController(__CLASS__, 'tooltip', $params);
        }

        parent::assertNotEmbeddedImageRequest();

        $user = parent::assertViewableUser($params->user_id);

        $page = $this->filterPage($params->page);
        $perPage = parent::options()->messagesPerPage;

        parent::assertCanonicalUrl(parent::buildLink('members', $user, ['page' => $page]));

        /** @var \XF\Repository\UserAlert $userAlertRepo */
        $userAlertRepo = parent::repository('XF:UserAlert');

        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = parent::repository('XF:Attachment');

        if ($user->canViewPostsOnProfile())
        {
            $profilePostRepo = parent::getProfilePostRepo();
            $profilePostFinder = $profilePostRepo->findProfilePostsOnProfile($user, [
                'allowOwnPending' => parent::hasContentPendingApproval()
            ]);
            if($page == 1)
            {
                $stickyProfilePostFinder = clone $profilePostFinder;
                $stickyProfilePosts = $stickyProfilePostFinder
                    ->where('bear_addon_pinned_profile_posts_sticky', 1)->fetch();
            }
            else
            {
                $stickyProfilePosts = null;
            }

            $profilePostFinder
                ->where('bear_addon_pinned_profile_posts_sticky', 0);
            $profilePosts = $profilePostFinder->limitByPage($page, $perPage)
                ->fetch();

            $allProfilePosts = $profilePosts;
            if($stickyProfilePosts)
            {
                $allProfilePosts = $profilePosts->merge($stickyProfilePosts);
            }

            $attachmentRepo->addAttachmentsToContent($allProfilePosts, 'profile_post');

            $total = $profilePostFinder->total();

            $isRobot = parent::isRobot();
            $profilePostRepo->addCommentsToProfilePosts($allProfilePosts, $isRobot);

            /** @var \XF\Repository\Unfurl $unfurlRepo */
            $unfurlRepo = parent::repository('XF:Unfurl');
            $unfurlRepo->addUnfurlsToContent($allProfilePosts, $isRobot);

            $commentIds = [];
            foreach ($allProfilePosts AS $profilePost)
            {
                if ($profilePost->LatestComments)
                {
                    $commentIds = array_merge($commentIds, $profilePost->LatestComments->keys());
                }
            }

            $userAlertRepo->markUserAlertsReadForContent('profile_post', $allProfilePosts->keys());
            $userAlertRepo->markUserAlertsReadForContent('profile_post_comment', $commentIds);
        }
        else
        {
            $total = 0;
            $profilePosts = parent::em()->getEmptyCollection();
            $stickyProfilePosts = parent::em()->getEmptyCollection();
            $allProfilePosts = parent::em()->getEmptyCollection();
        }

        parent::assertValidPage($page, $perPage, $total, 'members', $user);

        $visitor = \XF::visitor();
        if ($user->user_id != $visitor->user_id)
        {
            $userAlertRepo->markUserAlertsReadForContent('user', $visitor->user_id, 'following');
        }

        $canInlineMod = false;
        $canViewAttachments = false;
        $profilePostAttachData = [];
        foreach ($allProfilePosts AS $profilePost)
        {
            if (!$canInlineMod && $profilePost->canUseInlineModeration())
            {
                $canInlineMod = true;
            }
            if (!$canViewAttachments && $profilePost->canViewAttachments())
            {
                $canViewAttachments = true;
            }
            if ($profilePost->canUploadAndManageAttachments())
            {
                $profilePostAttachData[$profilePost->profile_post_id] = $attachmentRepo->getEditorData('profile_post_comment', $profilePost);
            }
        }

        if ($user->canUploadAndManageAttachmentsOnProfile())
        {
            $attachmentData = $attachmentRepo->getEditorData('profile_post', $user);
        }
        else
        {
            $attachmentData = null;
        }

        if ($user->canViewLatestActivity() && !$user->canViewPostsOnProfile())
        {
            $maxItems = parent::options()->newsFeedMaxItems;

            $newsFeedRepo = parent::repository('XF:NewsFeed');

            $newsFeedFinder = $newsFeedRepo->findMembersActivity($user);

            $newsFeed = $newsFeedFinder->fetch($maxItems * 2);
            $newsFeedRepo->addContentToNewsFeedItems($newsFeed);

            $newsFeed = $newsFeed->filterViewable();
            $newsFeed = $newsFeed->slice(0, $maxItems);

            $newsFeedItems = $newsFeed;
            $newsFeedOldestItemId = $newsFeed->count() ? min(array_keys($newsFeed->toArray())) : 0;
        }
        else
        {
            $newsFeedItems = [];
            $newsFeedOldestItemId = 0;
        }

        $viewParams = [
            'user' => $user,

            'profilePosts' => $profilePosts,
            'stickyProfilePosts' => $stickyProfilePosts,
            'canInlineMod' => $canInlineMod,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,

            'attachmentData' => $attachmentData,
            'canViewAttachments' => $canViewAttachments,
            'profilePostAttachData' => $profilePostAttachData,

            'newsFeedItems' => $newsFeedItems,
            'newsFeedOldestItemId' => $newsFeedOldestItemId,
        ];
        return parent::view('XF:Member\View', 'member_view', $viewParams);
    }

    public function actionTooltip(ParameterBag $params)
    {
        $reply = parent::actionTooltip($params);

        if (!$reply instanceof \XF\Mvc\Reply\View)
        {
            return $reply;
        }

        if($this->options()->BearAddonPinnedProfilePostsDisplayOnTooltip)
        {
            $user = parent::assertViewableUser($params->user_id);

            $profilePostRepo = parent::getProfilePostRepo();
            $profilePostFinder = $profilePostRepo->findProfilePostsOnProfile($user, [
                'allowOwnPending' => parent::hasContentPendingApproval()
            ])
                ->where('bear_addon_pinned_profile_posts_sticky', 1);

            $lastStickiedPost = $profilePostFinder->fetchOne();

            $reply->setParam('last_stickied_post', $lastStickiedPost);
            return $reply;
        }

        $reply->setParam('last_stickied_post', null);
        return $reply;
    }
}