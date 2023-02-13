<?php

namespace BearAddon\PinnedProfilePosts\XF\Service\ProfilePost;

use XF\Mvc\ParameterBag;

class Editor extends XFCP_Editor
{
    public function setSticky($sticky)
    {
        $this->profilePost->bear_addon_pinned_profile_posts_sticky = $sticky;
    }
}