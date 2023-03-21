<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace core;

/**
 * A set of tests for some of the gd functionality within Moodle.
 *
 * @package    core
 * @category   test
 * @copyright  2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gdlib_test extends \advanced_testcase {

    private $fixturepath = null;

    public function setUp(): void {
        $this->fixturepath = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR;
    }

    public function test_generate_image_thumbnail() {
        global $CFG;
        require_once($CFG->libdir . '/gdlib.php');

        // Test with meaningless data.

        // Now use a fixture.
        $pngpath = $this->fixturepath . 'gd-logo.png';
        $pngthumb = generate_image_thumbnail($pngpath, 24, 24);
        $this->assertTrue(is_string($pngthumb));

        // And check that the generated image was of the correct proportions and mimetype.
        $imageinfo = getimagesizefromstring($pngthumb);
        $this->assertEquals(24, $imageinfo[0]);
        $this->assertEquals(24, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);
    }

    public function test_generate_image_thumbnail_from_string() {
        global $CFG;
        require_once($CFG->libdir . '/gdlib.php');

        // Test with meaningless data.

        // First empty values.
        $this->assertFalse(generate_image_thumbnail_from_string('', 24, 24));
        $this->assertFalse(generate_image_thumbnail_from_string('invalid', 0, 24));
        $this->assertFalse(generate_image_thumbnail_from_string('invalid', 24, 0));

        // Now an invalid string.
        $this->assertFalse(generate_image_thumbnail_from_string('invalid', 24, 24));

        // Now use a fixture.
        $pngpath = $this->fixturepath . 'gd-logo.png';
        $pngdata = file_get_contents($pngpath);
        $pngthumb = generate_image_thumbnail_from_string($pngdata, 24, 24);
        $this->assertTrue(is_string($pngthumb));

        // And check that the generated image was of the correct proportions and mimetype.
        $imageinfo = getimagesizefromstring($pngthumb);
        $this->assertEquals(24, $imageinfo[0]);
        $this->assertEquals(24, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);
    }

    public function test_resize_image() {
        global $CFG;
        require_once($CFG->libdir . '/gdlib.php');

        $pngpath = $this->fixturepath . 'gd-logo.png';

        // Preferred height.
        $newpng = resize_image($pngpath, null, 24);
        $this->assertTrue(is_string($newpng));
        $imageinfo = getimagesizefromstring($newpng);
        $this->assertEquals(89, $imageinfo[0]);
        $this->assertEquals(24, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);

        // Preferred width.
        $newpng = resize_image($pngpath, 100, null);
        $this->assertTrue(is_string($newpng));
        $imageinfo = getimagesizefromstring($newpng);
        $this->assertEquals(100, $imageinfo[0]);
        $this->assertEquals(26, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);

        // Preferred width and height.
        $newpng = resize_image($pngpath, 50, 50);
        $this->assertTrue(is_string($newpng));
        $imageinfo = getimagesizefromstring($newpng);
        $this->assertEquals(50, $imageinfo[0]);
        $this->assertEquals(13, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);
    }

    public function test_resize_image_from_image() {
        global $CFG;
        require_once($CFG->libdir . '/gdlib.php');

        $pngpath = $this->fixturepath . 'gd-logo.png';
        $origimageinfo = getimagesize($pngpath);
        $imagecontent = file_get_contents($pngpath);

        // Preferred height.
        $imageresource = imagecreatefromstring($imagecontent);
        $newpng = resize_image_from_image($imageresource, $origimageinfo, null, 24);
        $this->assertTrue(is_string($newpng));
        $imageinfo = getimagesizefromstring($newpng);
        $this->assertEquals(89, $imageinfo[0]);
        $this->assertEquals(24, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);

        // Preferred width.
        $imageresource = imagecreatefromstring($imagecontent);
        $newpng = resize_image_from_image($imageresource, $origimageinfo, 100, null);
        $this->assertTrue(is_string($newpng));
        $imageinfo = getimagesizefromstring($newpng);
        $this->assertEquals(100, $imageinfo[0]);
        $this->assertEquals(26, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);

        // Preferred width and height.
        $imageresource = imagecreatefromstring($imagecontent);
        $newpng = resize_image_from_image($imageresource, $origimageinfo, 50, 50);
        $this->assertTrue(is_string($newpng));
        $imageinfo = getimagesizefromstring($newpng);
        $this->assertEquals(50, $imageinfo[0]);
        $this->assertEquals(13, $imageinfo[1]);
        $this->assertEquals('image/png', $imageinfo['mime']);
    }

    /**
     * Test that the process_new_icon() method correctly rotates and generates an
     * icon based on the source image EXIF data.
     *
     * @covers ::process_new_icon()
     * @dataProvider icon_images_provider
     *
     * @param   int     $controlangle Angle to be used when rotating the control image
     * @param   string  $imagepath Path to the required image
     * @param   int   $imageitemid ID of created item
     */
    public function test_rotation_process_new_icon(int $controlangle, string $imagepath, int $imageitemid): void {
        $this->resetAfterTest();

        // Check if required JPEG functions for this test exist.
        if (!function_exists('imagecreatefromjpeg')) {
            $this->markTestSkipped('JPEG not supported on this server.');
        }

        // Check if required EXIF functions for this test exist.
        if (!function_exists("exif_read_data")) {
            $this->markTestSkipped('This test requires exif support.');
        }

        // Check if the given file exists.
        if (!is_file($imagepath)) {
            $this->markTestSkipped('Required fixture image does not exist.');
        }

        // Require libs.
        global $CFG;
        require_once($CFG->libdir . '/gdlib.php');

        $fs = get_file_storage();

        // Get image data from given filepath.
        $imageinfo = getimagesize($imagepath);

        // Save some info in a better format for easier use later.
        $image = new \stdClass();
        $image->width  = $imageinfo[0];
        $image->height = $imageinfo[1];

        if ($controlangle == 90 || $controlangle == 270) {
            $image->width = $imageinfo[1];
            $image->height = $imageinfo[0];
        }

        // Make and rotate a control image to be used for comparison.
        $control = imagecreatefromjpeg($imagepath);
        $control = imagerotate($control, $controlangle, 0);

        // Create blank 100x100 image.
        if (function_exists('imagecreatetruecolor')) {
            $control1 = imagecreatetruecolor(100, 100);
        } else {
            $control1 = imagecreate(100, 100);
        }

        // Calculate copy coordinates.
        $cx = floor($image->width / 2);
        $cy = floor($image->height / 2);

        if ($image->width < $image->height) {
            $half = floor($image->width / 2.0);
        } else {
            $half = floor($image->height / 2.0);
        }

        // Use imagecopybicubic() to resize control image.
        imagecopybicubic($control1, $control, 0, 0, $cx - $half, $cy - $half, 100, 100, $half * 2, $half * 2);

        // Stringify control image.
        ob_start();
        imagejpeg($control1, null, 90);
        $contentsexpected = ob_get_clean();

        // Save control image to file system.
        $fsdata = [
            'contextid' => \context_user::instance(2, MUST_EXIST)->id,
            'component' => 'user',
            'filearea' => 'icon',
            'itemid' => 100,
            'filepath' => '/',
            'filename' => 'f1.jpg',
        ];
        $fs->delete_area_files($fsdata['contextid'], $fsdata['component'], $fsdata['filearea'], $fsdata['itemid']);
        $controlsaved = $fs->create_file_from_string($fsdata, $contentsexpected);

        // Use process_new_icon() function to create a new icon.
        $iconid = process_new_icon(
            \context_user::instance(2, MUST_EXIST),
            'user',
            'icon',
            $imageitemid,
            $imagepath
        );

        // Assert that $iconid was returned.
        $this->assertTrue($iconid !== false);

        // Fetch created icon by file ID.
        $icon = $fs->get_file_by_id($iconid);

        // Stringify control image returned by create_file_from_string.
        ob_start();
        imagejpeg(imagecreatefromstring($controlsaved->get_content()), null, 90);
        $contentsexpected = ob_get_clean();

        // Stringify created icon.
        ob_start();
        imagejpeg(imagecreatefromstring($icon->get_content()), null, 90);
        $contentsactual = ob_get_clean();

        // Assert that icon created with process_new_icon() matches the one created manually.
        $this->assertEquals($contentsexpected, $contentsactual);
    }

    /**
     * Data provider for test_rotation_process_new_icon().
     *
     * @return array
     */
    public static function icon_images_provider(): array {
        global $CFG;

        return [
            [0, $CFG->dirroot . "/lib/filestorage/tests/fixtures/minEXIF/h/JPEG1.jpeg", 1],
            [180, $CFG->dirroot . "/lib/filestorage/tests/fixtures/minEXIF/h/JPEG3.jpeg", 2],
            [270, $CFG->dirroot . "/lib/filestorage/tests/fixtures/minEXIF/h/JPEG6.jpeg", 3],
            [90, $CFG->dirroot . "/lib/filestorage/tests/fixtures/minEXIF/h/JPEG8.jpeg", 4],
            [0, $CFG->dirroot . "/lib/filestorage/tests/fixtures/minEXIF/v/JPEG1.jpeg", 5],
            [180, $CFG->dirroot . "/lib/filestorage/tests/fixtures/minEXIF/v/JPEG3.jpeg", 6],
            [270, $CFG->dirroot . "/lib/filestorage/tests/fixtures/minEXIF/v/JPEG6.jpeg", 7],
            [90, $CFG->dirroot . "/lib/filestorage/tests/fixtures/minEXIF/v/JPEG8.jpeg", 8],
            [0, $CFG->dirroot . "/lib/filestorage/tests/fixtures/fullEXIF/h/JPEG1.jpeg", 9],
            [180, $CFG->dirroot . "/lib/filestorage/tests/fixtures/fullEXIF/h/JPEG3.jpeg", 10],
            [270, $CFG->dirroot . "/lib/filestorage/tests/fixtures/fullEXIF/h/JPEG6.jpeg", 11],
            [90, $CFG->dirroot . "/lib/filestorage/tests/fixtures/fullEXIF/h/JPEG8.jpeg", 12],
            [0, $CFG->dirroot . "/lib/filestorage/tests/fixtures/fullEXIF/v/JPEG1.jpeg", 13],
            [180, $CFG->dirroot . "/lib/filestorage/tests/fixtures/fullEXIF/v/JPEG3.jpeg", 14],
            [270, $CFG->dirroot . "/lib/filestorage/tests/fixtures/fullEXIF/v/JPEG6.jpeg", 15],
            [90, $CFG->dirroot . "/lib/filestorage/tests/fixtures/fullEXIF/v/JPEG8.jpeg", 16],
            [0, $CFG->dirroot . "/lib/filestorage/tests/fixtures/incorrectEXIF/h/JPEG0.jpeg", 17],
            [0, $CFG->dirroot . "/lib/filestorage/tests/fixtures/incorrectEXIF/h/JPEG10.jpeg", 18],
            [0, $CFG->dirroot . "/lib/filestorage/tests/fixtures/incorrectEXIF/h/JPEG0MissingEXIFH.jpeg", 19],
            [0, $CFG->dirroot . "/lib/filestorage/tests/fixtures/incorrectEXIF/h/JPEG10MissingEXIFH.jpeg", 20],
            [0, $CFG->dirroot . "/lib/filestorage/tests/fixtures/incorrectEXIF/v/JPEG0.jpeg", 21],
            [0, $CFG->dirroot . "/lib/filestorage/tests/fixtures/incorrectEXIF/v/JPEG0MissingEXIFH.jpeg", 22],
            [180, $CFG->dirroot . "/lib/filestorage/tests/fixtures/partEXIF/h/JPEGMissingEXIFH.jpeg", 23],
            [180, $CFG->dirroot . "/lib/filestorage/tests/fixtures/partEXIF/h/JPEGMissingEXIFW.jpeg", 24],
            [180, $CFG->dirroot . "/lib/filestorage/tests/fixtures/partEXIF/v/JPEGMissingEXIFH.jpeg", 25],
            [0, $CFG->dirroot . "/lib/filestorage/tests/fixtures/noEXIF/h/JPEG1.jpeg", 26],
            [0, $CFG->dirroot . "/lib/filestorage/tests/fixtures/noEXIF/v/JPEG1.jpeg", 27],
        ];
    }
}
