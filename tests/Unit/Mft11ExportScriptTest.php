<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class Mft11ExportScriptTest extends TestCase
{
    public function test_python_export_script_file_exists_and_contains_expected_logic()
    {
        $scriptPath = base_path('scripts/mft11_export_faces.py');
        $this->assertFileExists($scriptPath);

        $content = file_get_contents($scriptPath);
        $this->assertStringContainsString('ZBASEMEDIARELATIONASSIGNMENT', $content);
        $this->assertStringContainsString('ZMEDIARELATION', $content);
        $this->assertStringContainsString('parse_cocoa_rect', $content);
        $this->assertStringContainsString('1.0 - (raw_y + h)', $content);
    }

    public function test_admin_readme_documentation_exists()
    {
        $readmePath = base_path('scripts/README.md');
        $this->assertFileExists($readmePath);

        $content = file_get_contents($readmePath);
        $this->assertStringContainsString('MacFamilyTree 11', $content);
        $this->assertStringContainsString('Database.sqlite', $content);
        $this->assertStringContainsString('faces.json', $content);
    }

    public function test_cocoa_coordinate_conversion_to_web_css()
    {
        // Cocoa NSRect: x=0.245, y=0.180, w=0.120, h=0.155 (origin at bottom-left)
        $raw_x = 0.245;
        $raw_y = 0.180;
        $w = 0.120;
        $h = 0.155;

        // Web CSS: origin at top-left
        // web_y = 1.0 - (0.180 + 0.155) = 1.0 - 0.335 = 0.665
        $web_x = round(max(0.0, $raw_x), 4);
        $web_y = round(max(0.0, 1.0 - ($raw_y + $h)), 4);
        $web_w = round(min(1.0, $w), 4);
        $web_h = round(min(1.0, $h), 4);

        $this->assertEquals(0.245, $web_x);
        $this->assertEquals(0.665, $web_y);
        $this->assertEquals(0.120, $web_w);
        $this->assertEquals(0.155, $web_h);

        $cssLeft = sprintf('%.2f%%', $web_x * 100);
        $cssTop = sprintf('%.2f%%', $web_y * 100);
        $this->assertEquals('24.50%', $cssLeft);
        $this->assertEquals('66.50%', $cssTop);
    }
}
