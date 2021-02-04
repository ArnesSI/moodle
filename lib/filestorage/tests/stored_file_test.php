<?php
// This file is part of Moodle - https://moodle.org/
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

use advanced_testcase;
use context_system;

/**
 * Unit tests for lib/filestorage/stored_file.php.
 *
 * @package    core_files
 * @category   test
 * @covers     \stored_file
 * @copyright  2022 Mikhail Golenkov <mikhailgolenkov@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stored_file_test extends advanced_testcase {

    /**
     * Helper to create a stored file object from a file on the filesystem.
     *
     * @param   string  $imagefolder Name of the folder the image is in (folders are located in lib/filestorage/tests/fixtures)
     * @param   string  $imagename Name of the image file in the folder
     * @param   int   $imageitemid ID of created item
     * @return \stored_file
     */
    protected function get_stored_file(string $imagefolder, string $imagename, int $imageitemid): \stored_file {
        global $CFG;

        $filepath = $CFG->dirroot . "/lib/filestorage/tests/fixtures/" . $imagefolder . "/" . $imagename;
        $syscontext = context_system::instance();
        $filerecord = [
            'contextid' => $syscontext->id,
            'component' => 'core',
            'filearea'  => 'unittest',
            'itemid'    => $imageitemid,
            'filepath'  => '/images/',
            'filename'  => $imagename,
        ];

        $fs = get_file_storage();
        $image = $fs->create_file_from_pathname($filerecord, $filepath);

        return $image;
    }

    /**
     * Images that have orientation value set to 1 do not require
     * rotation, but should return size data.
     *
     * @covers ::rotate_image()
     * @dataProvider correct_orientation_images_provider
     *
     * @param   string  $imagefolder Folder that contains the required image
     * @param   int  $expectedwidth Expected image width
     * @param   int   $expectedheight Expected image height
     */
    public function test_rotate_image_with_correct_orientation(string $imagefolder, int $expectedwidth, int $expectedheight): void {
        $this->resetAfterTest(true);
        if (!function_exists("exif_read_data")) {
            $this->markTestSkipped('This test requires exif support.');
        }

        // Use rotate_image() function to rotate image.
        $image = self::get_stored_file($imagefolder, 'JPEG1.jpeg', 1);
        list ($rotateddata, $size) = $image->rotate_image();

        // Assert that $rotatedata was returned.
        $this->assertFalse(empty($rotateddata));

        // Assert that the correct size data was returned.
        $this->assertEquals($size, ["width" => $expectedwidth, "height" => $expectedheight]);

        ob_start();
        imagejpeg(imagecreatefromstring($image->get_content()));
        $contentsexpected = ob_get_clean();

        ob_start();
        imagejpeg($rotateddata);
        $contentsactual = ob_get_clean();

        // Assert that image rotated with rotate_image() remains unchanged.
        $this->assertEquals($contentsexpected, $contentsactual);
    }

    /**
     * Data provider for test_rotate_image_with_correct_orientation().
     *
     * @return array
     */
    public static function correct_orientation_images_provider(): array {
        return [
            ["minEXIF/h", 320, 240],
            ["minEXIF/v", 240, 320],
            ["fullEXIF/h", 321, 241],
            ["fullEXIF/v", 241, 321],
        ];
    }

    /**
     * Test that the rotate_image() method correctly rotates an image
     * that is supposed to be rotated.
     *
     * @covers ::rotate_image()
     * @dataProvider images_provider
     *
     * @param   int  $controlangle Angle to be used when generating a control image
     * @param   string  $imagefolder Folder that contains the required image
     * @param   string  $imagename Filename of required image
     * @param   int   $imageitemid ID of created item
     * @param   int   $expectedwidth Expected image width
     * @param   int   $expectedheight Expected image height
     */
    public function test_rotate_image(int $controlangle, string $imagefolder, string $imagename, int $imageitemid,
        int $expectedwidth, int $expectedheight): void {
        $this->resetAfterTest(true);
        if (!function_exists("exif_read_data")) {
            $this->markTestSkipped('This test requires exif support.');
        }

        // Get stored file with orientation set to 1.
        $control = self::get_stored_file($imagefolder, "JPEG1.jpeg", 100);

        // Use imagerotate function to get control image with expected rotation.
        $controlrotated = imagerotate(imagecreatefromstring($control->get_content()), $controlangle, 0);

        // Use rotate_image() function to rotate image.
        $image = self::get_stored_file($imagefolder, $imagename, $imageitemid);
        list ($rotateddata, $size) = $image->rotate_image();

        // Assert that $rotatedata was returned.
        $this->assertFalse(empty($rotateddata));

        // Assert that the correct size data was returned.
        $this->assertEquals($size, ["width" => $expectedwidth, "height" => $expectedheight]);

        ob_start();
        imagejpeg($controlrotated);
        $contentsexpected = ob_get_clean();

        ob_start();
        imagejpeg($rotateddata);
        $contentsactual = ob_get_clean();

        // Assert that image rotated with rotate_image() matches control image.
        $this->assertEquals($contentsexpected, $contentsactual);
    }

    /**
     * Data provider for test_rotate_image().
     *
     * @return array
     */
    public static function images_provider(): array {
        return [
            [180, "minEXIF/h", "JPEG3.jpeg", 3, 320, 240],
            [270, "minEXIF/h", "JPEG6.jpeg", 6, 240, 320],
            [90, "minEXIF/h", "JPEG8.jpeg", 8, 240, 320],
            [180, "minEXIF/v", "JPEG3.jpeg", 3, 240, 320],
            [270, "minEXIF/v", "JPEG6.jpeg", 6, 320, 240],
            [90, "minEXIF/v", "JPEG8.jpeg", 8, 320, 240],
            [180, "fullEXIF/h", "JPEG3.jpeg", 3, 321, 241],
            [270, "fullEXIF/h", "JPEG6.jpeg", 6, 241, 321],
            [90, "fullEXIF/h", "JPEG8.jpeg", 8, 241, 321],
            [180, "fullEXIF/v", "JPEG3.jpeg", 3, 241, 321],
            [270, "fullEXIF/v", "JPEG6.jpeg", 6, 321, 241],
            [90, "fullEXIF/v", "JPEG8.jpeg", 8, 321, 241],
        ];
    }

    /**
     * Test that the rotate_image() method correctly handles an image
     * that has no EXIF data set. Image should not be rotated, size should be
     * returned from COMPUTED property.
     *
     * @covers ::rotate_image()
     * @dataProvider no_orientation_images_provider
     *
     * @param   string  $imagefolder Folder that contains the required image
     * @param   int   $expectedwidth Expected image width
     * @param   int   $expectedheight Expected image height
     */
    public function test_rotation_no_exif(string $imagefolder, int $expectedwidth, int $expectedheight): void {
        $this->resetAfterTest(true);

        $image = self::get_stored_file($imagefolder, "JPEG1.jpeg", 1);
        list ($rotateddata, $size) = $image->rotate_image();

        $this->assertFalse($rotateddata);
        $this->assertEquals($size, ["width" => $expectedwidth, "height" => $expectedheight]);
    }

    /**
     * Data provider for test_rotation_no_exif().
     *
     * @return array
     */
    public static function no_orientation_images_provider(): array {
        return [
            ["noEXIF/h", 320, 240],
            ["noEXIF/v", 240, 320],
        ];
    }

    /**
     * Test that the rotate_image() method correctly handles an image
     * that has partial EXIF data set. Image should be rotated, size should be
     * returned from EXIF. If EXIF width and/or height not set, return size from COMPUTED.
     *
     * @covers ::rotate_image()
     * @dataProvider partial_orientation_images_provider
     *
     * @param   string  $imagefolder Folder that contains the required image
     * @param   string  $imagename Filename of required image
     * @param   int   $imageitemid ID of created item
     * @param   int   $expectedwidth Expected image width
     * @param   int   $expectedheight Expected image height
     */
    public function test_rotate_image_partial_exif(string $imagefolder, string $imagename, int $imageitemid,
        int $expectedwidth, int $expectedheight): void {
        $this->resetAfterTest(true);
        if (!function_exists("exif_read_data")) {
            $this->markTestSkipped('This test requires exif support.');
        }

        $image = self::get_stored_file($imagefolder, $imagename, $imageitemid);

        // Use imagerotate function to get control image with expected rotation.
        $controlrotated = imagerotate(imagecreatefromstring($image->get_content()), 180, 0);

        // Use rotate_image() function to rotate image.
        list ($rotateddata, $size) = $image->rotate_image();

        // Assert that $rotatedata was returned.
        $this->assertFalse(empty($rotateddata));

        // Assert that image rotated with rotate_image() matches control image.
        $this->assertEquals($size, ["width" => $expectedwidth, "height" => $expectedheight]);

        ob_start();
        imagejpeg($controlrotated);
        $contentsexpected = ob_get_clean();

        ob_start();
        imagejpeg($rotateddata);
        $contentsactual = ob_get_clean();

        // Assert that the correct size data was returned.
        $this->assertEquals($contentsexpected, $contentsactual);
    }

    /**
     * Data provider for test_rotate_image_partial_exif().
     *
     * @return array
     */
    public static function partial_orientation_images_provider(): array {
        return [
            ["partEXIF/h", "JPEGMissingEXIFH.jpeg", 100, 320, 240],
            ["partEXIF/h", "JPEGMissingEXIFW.jpeg", 101, 320, 240],
            ["partEXIF/v", "JPEGMissingEXIFH.jpeg", 102, 240, 320],
        ];
    }

    /**
     * Test that the rotate_image() method correctly handles an image
     * that has incorrect EXIF Orientation data set. Image should not be rotated, size should be
     * returned from EXIF. If EXIF width and/or height not set, return size from COMPUTED.
     *
     * @covers ::rotate_image()
     * @dataProvider incorrect_orientation_images_provider
     *
     * @param   string  $imagefolder Folder that contains the required image
     * @param   string  $imagename Filename of required image
     * @param   int   $imageitemid ID of created item
     * @param   int   $expectedwidth Expected image width
     * @param   int   $expectedheight Expected image height
     */
    public function test_rotate_image_exif_orientation_incorrect(string $imagefolder, string $imagename,
        int $imageitemid, int $expectedwidth, int $expectedheight): void {
        $this->resetAfterTest(true);
        if (!function_exists("exif_read_data")) {
            $this->markTestSkipped('This test requires exif support.');
        }

        $image = self::get_stored_file($imagefolder, $imagename, $imageitemid);
        list ($rotateddata, $size) = $image->rotate_image();

        $this->assertFalse($rotateddata);
        $this->assertEquals($size, ["width" => $expectedwidth, "height" => $expectedheight]);
    }

    /**
     * Data provider for test_rotate_image_exif_orientation_incorrect().
     *
     * @return array
     */
    public static function incorrect_orientation_images_provider(): array {
        return [
            ["incorrectEXIF/h", "JPEG0.jpeg", 0, 321, 241],
            ["incorrectEXIF/h", "JPEG10.jpeg", 10, 321, 241],
            ["incorrectEXIF/h", "JPEG0MissingEXIFH.jpeg", 3, 320, 240],
            ["incorrectEXIF/h", "JPEG10MissingEXIFH.jpeg", 4, 320, 240],
            ["incorrectEXIF/v", "JPEG0.jpeg", 0, 241, 321],
            ["incorrectEXIF/v", "JPEG0MissingEXIFH.jpeg", 4, 240, 320],
        ];
    }

    /**
     * Ensure that get_content_file_handle returns a valid file handle.
     *
     * @covers ::get_psr_stream
     */
    public function test_get_psr_stream(): void {
        global $CFG;
        $this->resetAfterTest();

        $filename = 'testimage.jpg';
        $filepath = $CFG->dirroot . '/lib/filestorage/tests/fixtures/' . $filename;
        $filerecord = [
            'contextid' => context_system::instance()->id,
            'component' => 'core',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $filename,
        ];
        $fs = get_file_storage();
        $file = $fs->create_file_from_pathname($filerecord, $filepath);

        $stream = $file->get_psr_stream();
        $this->assertInstanceOf(\Psr\Http\Message\StreamInterface::class, $stream);
        $this->assertEquals(file_get_contents($filepath), $stream->getContents());
        $this->assertFalse($stream->isWritable());
        $stream->close();
    }

}
