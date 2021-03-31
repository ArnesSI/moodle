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

/**
 * Unit tests for /lib/filestorage/stored_file.php::rotate_image().
 *
 * @package core_files
 * @copyright 2021 Arnes
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


global $CFG;
require_once($CFG->libdir . '/filestorage/stored_file.php');

/**
 * Unit tests for the rotate_image function.
 *
 * @copyright 2021 Arnes
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rotate_image_testcase extends advanced_testcase {

    /**
     * Skip tests is the 'exif_read_data' function doesn't exist.
     */
    protected function setUp(): void {
        if (!function_exists("exif_read_data")) {
            $this->markTestSkipped('All tests in this file require exif-support.');
        }
    }

    /**
     * Helper to create a stored file object from a file on the filesystem.
     *
     * @param   string  $imagefolder Name of the folder the image is in (folders are located in lib/filestorage/tests/fixtures)
     * @param   string  $imagename Name of the image file in the folder
     * @param   array   $itemid ID of created item
     * @return stored_file
     */
    protected function get_stored_file($imagefolder, $imagename, $itemid) {
        global $CFG;

        $filepath = $CFG->dirroot."/lib/filestorage/tests/fixtures/".$imagefolder."/".$imagename;
        $syscontext = context_system::instance();
        $filerecord = array(
            'contextid' => $syscontext->id,
            'component' => 'core',
            'filearea'  => 'unittest',
            'itemid'    => $itemid,
            'filepath'  => '/images/',
            'filename'  => $imagename,
        );

        $fs = get_file_storage();
        $image = $fs->create_file_from_pathname($filerecord, $filepath);

        return $image;
    }

    public function test_rotation_no_exif() {
        $this->resetAfterTest(true);

        // No EXIF data set, should return size from COMPUTED.
        $image = self::get_stored_file("NoEXIF", "JPEG1.jpeg", 1);
        list ($rotateddata, $size) = $image->rotate_image();

        $this->assertFalse($rotateddata);
        $this->assertEquals($size, ["width" => 320, "height" => 240]);
    }

    /**
     * @dataProvider exif_rotation_orientation_1_provider
     */
    public function test_rotation_exif_orientation_1(string $imgfolder) {
        $this->resetAfterTest(true);

        $master = self::get_stored_file($imgfolder, "JPEG1.jpeg", 100);

        list ($rotateddata, $size) = $master->rotate_image();

        $this->assertFalse($rotateddata);
        $this->assertFalse($size);
    }

    public function exif_rotation_orientation_1_provider(): array {
        return [
            ["MinEXIF"],
            ["FullEXIF"],
        ];
    }

    /**
     * @dataProvider rotation_provider
     */
    public function test_rotation(string $imgfolder, int $masterangle,
        string $testimg, int $testitemid, int $expectedwidth, int $expectedheight) {
        $this->resetAfterTest(true);

        $master = self::get_stored_file($imgfolder, "JPEG1.jpeg", 100);
        $masterrotated = imagerotate(imagecreatefromstring($master->get_content()), $masterangle, 0);

        $image = self::get_stored_file($imgfolder, $testimg, $testitemid);
        list ($rotateddata, $size) = $image->rotate_image();

        ob_start();
        imagejpeg($masterrotated);
        $contentsexpected = ob_get_clean();

        ob_start();
        imagejpeg($rotateddata);
        $contentsactual = ob_get_clean();

        $this->assertFalse(empty($rotateddata));
        $this->assertEquals($contentsexpected, $contentsactual);
        $this->assertEquals($size, ["width" => $expectedwidth, "height" => $expectedheight]);
    }

    public function rotation_provider(): array {
        return [
            ["MinEXIF", 180, "JPEG3.jpeg", 3, 320, 240],
            ["MinEXIF", 270, "JPEG6.jpeg", 6, 240, 320],
            ["MinEXIF", 90, "JPEG8.jpeg", 8, 240, 320],
            ["FullEXIF", 180, "JPEG3.jpeg", 3, 321, 241],
            ["FullEXIF", 270, "JPEG6.jpeg", 6, 241, 321],
            ["FullEXIF", 90, "JPEG8.jpeg", 8, 241, 321],
        ];
    }

    /**
     * @dataProvider rand_exif_orientation_incorrect_provider
     */
    public function test_rotation_rand_exif_orientation_incorrect(string $imgname, int $imgid,
        int $expectedwidth, int $expectedheight) {
        $this->resetAfterTest(true);

        // Orientation incorrect, EXIF height and width set, should only return EXIF size.
        $image = self::get_stored_file("RandEXIF", $imgname, $imgid);
        list ($rotateddata, $size) = $image->rotate_image();

        $this->assertFalse($rotateddata);
        $this->assertEquals($size, ["width" => $expectedwidth, "height" => $expectedheight]);
    }

    public function rand_exif_orientation_incorrect_provider(): array {
        return [
            ["JPEGOri0.jpeg", 0, 321, 241],
            ["JPEGOri10.jpeg", 10, 321, 241],
            ["JPEGMissingEXIFHOri0.jpeg", 3, 320, 240],
            ["JPEGMissingEXIFHOri10.jpeg", 4, 320, 240]
        ];
    }

    /**
     * @dataProvider rand_exif_provider
     */
    public function test_rotation_rand_exif(string $imgname, int $imgid,
        int $expectedwidth, int $expectedheight) {
        $this->resetAfterTest(true);

        $master = self::get_stored_file("RandEXIF", $imgname, $imgid);
        $masterrotated = imagerotate(imagecreatefromstring($master->get_content()), 180, 0);

        list ($rotateddata, $size) = $master->rotate_image();

        ob_start();
        imagejpeg($masterrotated);
        $contentsexpected = ob_get_clean();

        ob_start();
        imagejpeg($rotateddata);
        $contentsactual = ob_get_clean();

        $this->assertFalse(empty($rotateddata));
        $this->assertEquals($contentsexpected, $contentsactual);
        $this->assertEquals($size, ["width" => $expectedwidth, "height" => $expectedheight]);
    }

    public function rand_exif_provider(): array {
        return [
            ["JPEGMissingEXIFH.jpeg", 100, 320, 240],
            ["JPEGMissingEXIFW.jpeg", 101, 320, 240],
        ];
    }
}
