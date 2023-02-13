<?php

namespace BearAddon\PinnedProfilePosts\XF\Entity;

class ProfilePost extends XFCP_ProfilePost
{
    public function canStickUnstick(): bool
    {
        // Always allow users to stick unstick if they have the stick/unstick any permission
        if(\XF::visitor()->hasPermission('profilePost','BAPPPStickUnstickAny'))
        {
            return true;
        }

        // If the user who posted the profile post isn't the user whos profile it is, don't allow them to stick/unstick
        // since they don't have the stick unstick any permission.
        // If the user who posted the post is the visitor and it is on their wall, and they have stick unstick own perms
        // allow them to stick/unstick
        return (
            \XF::visitor()->user_id == $this->user_id and
            $this->user_id != $this->profile_user_id and
            \XF::visitor()->hasPermission('profilePost','BAPPPStickUnstickOwn')
        );
    }
}