<?php

/*
 * This file is part of the appname project.
 *
 * (c) Romain Gautier <mail@romain.sh>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form\Data;

use App\Validator\Constraints\UniqueUsername;
use Symfony\Component\Validator\Constraints as Assert;

class RegisterUser
{
    /**
     * @Assert\NotBlank
     * @UniqueUsername
     * @Assert\Length(max = 100)
     */
    private $username;

    /**
     * @Assert\NotBlank
     * @Assert\Length(max = 100)
     */
    private $displayName;

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    /**
     * @param mixed $displayName
     */
    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }
}
