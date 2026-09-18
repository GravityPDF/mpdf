<?php


namespace Mpdf;

class CacheTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
	protected $basePath;
	protected $oldTmpMode;
	protected $oldUmask;
	protected $isWin = false;

	public function __construct($name = null, array $data = [], $dataName = '')
	{
		parent::__construct($name, $data, $dataName);

		$this->basePath = __DIR__ . "/../../";
		$this->isWin = DIRECTORY_SEPARATOR === '\\';
	}

	protected function path($relativeToRoot)
	{
		return $this->basePath . $relativeToRoot;
	}

	protected function set_up()
	{
		parent::set_up();

		$dir = $this->path("tmp");
		$this->oldTmpMode = fileperms($dir);
		$this->oldUmask = umask(0);
		chmod($dir, 0777);
	}

	protected function tear_down()
	{
		chmod($this->path("tmp"), $this->oldTmpMode);
		umask($this->oldUmask);

		parent::tear_down();
	}

	public function testCacheCreatesNonexistentDirectory()
	{
		$dir = $this->path("tmp/test1");

		try {
			new Cache($dir);

			$this->assertDirectoryExists($dir);

			$this->assertFileExists($dir);
		} finally {
			@rmdir($dir);
		}
	}

	public function testCreatedDirectoryIsWorldWritable()
	{
		$dir = $this->path("tmp/test2");

		try {
			new Cache($dir);

			$this->assertEquals(0777, fileperms($dir) & 0777);
		} finally {
			@rmdir($dir);
		}
	}

	public function testCacheCreatesDirectoriesRecursively()
	{
		$dir = $this->path("tmp/test3/subdir/subdir2");

		try {
			new Cache($dir);

			$this->assertDirectoryExists($dir);
		} finally {
			@rmdir($dir);
			@rmdir($this->path("tmp/test3/subdir"));
			@rmdir($this->path("tmp/test3"));
		}
	}

	public function testRecursivelyCreatedDirectoriesAreWorldWritable()
	{
		$dir = $this->path("tmp/test4/subdir/subdir2");

		try {
			new Cache($dir);

			foreach (array(
						 "tmp/test4/subdir/subdir2",
						 "tmp/test4/subdir",
						 "tmp/test4",
					 ) as $subdir) {
				$this->assertEquals(0777, fileperms($this->path($subdir)) & 0777);
			}
		} finally {
			@rmdir($dir);
			@rmdir($this->path("tmp/test4/subdir"));
			@rmdir($this->path("tmp/test4"));
		}
	}

	public function testCreatedDirectoryInheritsParentPermissions()
	{
		chmod($this->path("tmp"), 0755);

		$dir = $this->path("tmp/test5");

		try {
			new Cache($dir);

			$this->assertEquals($this->isWin ? 0777 : 0755, fileperms($dir) & 0777);
		} finally {
			@rmdir($dir);
		}
	}

	public function testRecursivelyCreatedDirectoriesInheritsParentPermissions()
	{
		chmod($this->path("tmp"), 0750);
		$dir = $this->path("tmp/test6/subdir/subdir2");

		try {
			new Cache($dir);

			foreach (array(
				"tmp/test6/subdir/subdir2",
				"tmp/test6/subdir",
				"tmp/test6",
			) as $subdir) {
				$this->assertEquals($this->isWin ? 0777 : 0750, fileperms($this->path($subdir)) & 0777);
			}
		} finally {
			@rmdir($dir);
			@rmdir($this->path("tmp/test6/subdir"));
			@rmdir($this->path("tmp/test6"));
		}
	}

	/**
	 * A caller that asks whether an entry is there and then reads it is handed nothing where another
	 * process expired it in between, so the read reports the miss itself - and reports it apart from an
	 * entry that is there and empty, which is what a font with no glyph map writes.
	 */
	public function testAnEntryThatIsNotThereReadsAsAMissRatherThanAsEmpty()
	{
		$dir = $this->path('tmp/test8');

		try {
			$cache = new Cache($dir);
			$cache->write('empty', '');

			$this->assertNull($cache->loadIfPresent('gone'));
			$this->assertSame('', $cache->loadIfPresent('empty'));
		} finally {
			@unlink($dir . '/empty');
			@rmdir($dir);
		}
	}

	/**
	 * The miss is an answer rather than a failure, and a cache that has never held the entry is the
	 * ordinary way of reaching it.
	 */
	public function testReadingAnEntryThatIsNotThereSaysNothingAtAll()
	{
		$dir = $this->path('tmp/test9');
		$raised = [];

		set_error_handler(static function ($severity, $message) use (&$raised) {
			$raised[] = $message;

			return true;
		});

		try {
			$cache = new Cache($dir);

			$this->assertNull($cache->loadIfPresent('gone'));
		} finally {
			restore_error_handler();
			@rmdir($dir);
		}

		$this->assertSame([], $raised);
	}

	public function testRecursivelyCreatedDirectoriesInheritsParentPermissionsAndOverridesUmask()
	{
		/* Set a umask and verify the directory is created with umask-affected permissions */
		umask(0077);
		mkdir($this->path("tmp/test7a"), 0755);
		$this->assertEquals($this->isWin ? 0777 : 0700, fileperms($this->path("tmp/test7a")) & 0777);

		/* Now verify the umask is ignored when creating the cache */
		chmod($this->path("tmp"), 0755);
		$dir = $this->path("tmp/test7b/subdir/subdir2");

		try {
			new Cache($dir);

			foreach (array(
						 "tmp/test7b/subdir/subdir2",
						 "tmp/test7b/subdir",
						 "tmp/test7b",
					 ) as $subdir) {
				$this->assertEquals($this->isWin ? 0777 : 0755, fileperms($this->path($subdir)) & 0777);
			}
		} finally {
			@rmdir($dir);
			@rmdir($this->path("tmp/test7b/subdir"));
			@rmdir($this->path("tmp/test7b"));
			@rmdir($this->path("tmp/test7a"));
		}
	}
}
