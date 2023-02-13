<?php

namespace BearAddon\PinnedProfilePosts;

use XF\Mvc\Entity\Entity;

class Listener
{
    public static function profilePostEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns['bear_addon_pinned_profile_posts_sticky'] = ['type' => Entity::BOOL, 'default' => false];
    }
}