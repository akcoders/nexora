<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer;
    }

    public function test_removes_scripts_event_handlers_and_unsafe_urls(): void
    {
        $html = $this->sanitizer->clean('<p onclick="steal()" style="color: red; position: fixed">Hi <a href="javascript:alert(1)">bad</a> <a href="java&#9;script:alert(2)">tab</a></p>'
            .'<script>alert(3)</script><img src="/storage/library/images/coil.png" onerror="alert(4)"><img src="data:image/png;base64,AAAA"><form><input name="x"></form>');

        $this->assertSame('<p style="color: red">Hi <a>bad</a> <a>tab</a></p><img src="/storage/library/images/coil.png">', $html);
    }

    public function test_keeps_safe_links_and_opens_external_ones_in_a_new_tab(): void
    {
        $this->assertSame(
            '<p><a href="https://example.com/a" target="_blank" rel="noopener noreferrer nofollow">site</a> <a href="mailto:help@example.com">mail</a></p>',
            $this->sanitizer->clean('<p><a href="https://example.com/a">site</a> <a href="mailto:help@example.com">mail</a></p>'),
        );
    }

    public function test_turns_quill_lists_into_semantic_lists_and_checklists(): void
    {
        $html = $this->sanitizer->clean('<ol><li data-list="ordered"><span class="ql-ui" contenteditable="false"></span>One</li><li data-list="ordered" class="ql-indent-1"><span class="ql-ui"></span>One a</li>'
            .'<li data-list="bullet"><span class="ql-ui"></span>Dot</li><li data-list="checked"><span class="ql-ui"></span>Done</li><li data-list="unchecked"><span class="ql-ui"></span>To do</li></ol>');

        $this->assertSame('<ol><li>One</li><li class="ql-indent-1">One a</li></ol><ul><li>Dot</li></ul><ul class="nx-checklist"><li data-checked="true">Done</li><li data-checked="false">To do</li></ul>', $html);
    }

    public function test_turns_quill_code_blocks_into_pre(): void
    {
        $this->assertSame(
            "<pre>L - phase\nN - neutral</pre>",
            $this->sanitizer->clean('<div class="ql-code-block-container" spellcheck="false"><div class="ql-code-block" data-language="plain">L - phase</div><div class="ql-code-block" data-language="plain">N - neutral</div></div>'),
        );
    }

    public function test_keeps_youtube_embeds_on_the_privacy_player_and_drops_other_frames(): void
    {
        $html = $this->sanitizer->clean('<iframe class="ql-video" src="https://www.youtube.com/embed/dQw4w9WgXcQ?showinfo=0"></iframe><iframe src="https://evil.example/embed"></iframe>');

        $this->assertSame('<iframe class="ql-video" src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0" frameborder="0" allowfullscreen="true" loading="lazy"></iframe>', $html);
    }

    #[TestWith(['<p><br></p>'])]
    #[TestWith(['<p> &nbsp; </p><p></p>'])]
    #[TestWith(['   '])]
    #[TestWith(['<script>alert(1)</script>'])]
    public function test_blank_editor_content_becomes_an_empty_string(string $html): void
    {
        $this->assertSame('', $this->sanitizer->clean($html));
    }

    public function test_plain_text_keeps_line_breaks_between_blocks(): void
    {
        $this->assertSame("Steps\nSwitch off\nOpen panel\nTip: wear gloves", $this->sanitizer->toPlainText('<h2>Steps</h2><ol><li>Switch off</li><li>Open&nbsp;panel</li></ol><blockquote>Tip: wear gloves</blockquote>'));
    }

    public function test_namespace_declarations_and_backslash_links_are_removed(): void
    {
        $html = (new HtmlSanitizer)->clean('<p xmlns:x="urn:evil">Hi <a href="//cdn.example.com/a">cdn</a> <a href="/\\evil.example">bad</a></p>');

        $this->assertStringNotContainsString('xmlns', $html);
        $this->assertStringContainsString('href="https://cdn.example.com/a"', $html);
        $this->assertStringContainsString('<a>bad</a>', $html);
    }
}
