<?php
// src/Service/AvatarService.php
namespace App\Service;

class AvatarService
{
    public function getAvatarUrl(string $name, int $size = 100): string
    {
        $initials = $this->extractInitials($name);
        $bgColor = $this->generateBackgroundColor($name);
        
        return sprintf(
            'https://ui-avatars.com/api/?name=%s&background=%s&color=fff&size=%d',
            urlencode($initials),
            substr($bgColor, 1),
            $size
        );
    }
    
    // ... méthodes extractInitials et generateBackgroundColor ...
}