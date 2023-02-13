<?php

namespace BearAddon\PinnedProfilePosts\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class ProfilePost extends XFCP_ProfilePost
{
    public function actionQuickStick(ParameterBag $params)
    {
        $maxStickiedPosts = $this->options()->BearAddonPinnedProfilePostsMaximumStickyPosts;

        $profile_post = parent::assertViewableProfilePost($params->profile_post_id);
        if (!$profile_post->canStickUnstick())
        {
            return parent::noPermission($error);
        }

        if($maxStickiedPosts != 0 && !$profile_post->bear_addon_pinned_profile_posts_sticky)
        {
            $profilePostRepo = parent::getProfilePostRepo();
            $totalStickiedPosts = $profilePostRepo->findProfilePostsOnProfile($profile_post->ProfileUser, [
                'allowOwnPending' => parent::hasContentPendingApproval()
            ])->where('bear_addon_pinned_profile_posts_sticky', 1)->total();

            if($totalStickiedPosts >= $maxStickiedPosts)
            {
                return parent::error(\XF::phrase('maximum_pinned_profile_posts'));
            }
        }

        $editor = $this->getEditorService($profile_post);

        if ($profile_post->bear_addon_pinned_profile_posts_sticky)
        {
            $editor->setSticky(false);
            $text = \XF::phrase('stick_profile_post');
        }
        else
        {
            $editor->setSticky(true);
            $text = \XF::phrase('unstick_profile_post');
        }

        if (!$editor->validate($errors))
        {
            return $this->error($errors);
        }

        $editor->save();

        $reply = parent::redirect(parent::getDynamicRedirect());
        $reply->setJsonParams([
            'text' => $text,
            'sticky' => $profile_post->bear_addon_pinned_profile_posts_sticky
        ]);
        return $reply;
    }

    /**
     * @param \XF\Entity\ProfilePost $profile_post
     *
     * @return \XF\Service\ProfilePost\Editor $editor
     */
    protected function getEditorService(\XF\Entity\ProfilePost $profile_post)
    {
        return $this->service('XF:ProfilePost\Editor', $profile_post);
    }
}