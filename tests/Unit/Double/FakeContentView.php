<?php

declare(strict_types=1);

namespace foun10\EasyCache\Tests\Unit\Double;

class FakeContentView extends FakeView
{
    /** @var string */
    private $contentId;

    public function __construct(string $contentId, string $viewId = 'viewid')
    {
        parent::__construct('content', $viewId);
        $this->contentId = $contentId;
    }

    public function getContentId(): string
    {
        return $this->contentId;
    }
}
