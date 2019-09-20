<?php
// namespace PluginTest\Stuff;
use ScssLibrary\ScssLibrary;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;

class ScssLibraryTest extends \PluginTestCase
{
	// Borrado recursivo de directorio
	static public function recurseRmdir($dir)
	{
		if(!is_dir("$dir")) return false;
		$files = array_diff(scandir($dir), array('.','..'));
		foreach ($files as $file) {
			(is_dir("$dir/$file")) ? self::recurseRmdir("$dir/$file") : unlink("$dir/$file");
		}
		return rmdir($dir);
	}

	// Probar inicialización, detectando que se carguen los hook
	public function test_init()
	{
		// Expectativas
		Actions\expectAdded('plugins_loaded');
		Filters\expectAdded('style_loader_src');
		Actions\expectAdded('wp_footer');
		Functions\expect('load_plugin_textdomain')
			->once();

		$stub = ScssLibrary::get_instance();
		$stub->plugin_setup();

		// ¿Se creó la instancia de clase ScssLibrary\ScssLibrary?
		self::assertInstanceOf('ScssLibrary\ScssLibrary', $stub);
	}

	// Múltiples pruebas a la función que crea el archivo css.
	public function test_style_loader_src()
	{
		// Inicializar clase
		$stub = ScssLibrary::get_instance();

		// Ignorar archivo css (fuera de este plugin porque tiene scss en el path)
		$file = 'assets/style.css';
		self::assertTrue($file == $stub->style_loader_src($file, 'test'));

		// Ignorar archivo css
		$file = WP_CONTENT_URL . 'style.css';
		self::assertTrue($file == $stub->style_loader_src($file, 'test'));

		// Ignorar archivo scss externo
		$file = 'http://localhost/assets/style.scss';
		self::assertTrue($file == $stub->style_loader_src($file, 'test'));

		// Crear archivo scss y comprobar que fue creado
		$file_css = WP_CONTENT_URL . 'style.scss';
		$file_scss = $stub->style_loader_src($file_css, 'test');
		self::assertTrue(strpos($file_scss, 'build/scss_library') != false);
		self::assertFileExists($file_scss);

		// Usar el archivo cuando ya existe el archivo
		$file_scss = $stub->style_loader_src($file_css, 'test');
		self::assertTrue(strpos($file_scss, 'build/scss_library') != false);

		// Crear archivo en Multisitio 2 y comprobar que fue creado
		Functions\when('is_multisite')->justReturn(true);
		Functions\when('get_blog_details')->justReturn(PATH_CURRENT_SITE . '/sitio_2/' );
		Functions\when('get_current_blog_id')->justReturn(2);
		$file_scss = $stub->style_loader_src($file_css, 'test');
		self::assertTrue(strpos($file_scss, 'build/scss_library/2') != false);
		self::assertFileExists($file_scss);

		// Borrar archivos compilados
		self::recurseRmdir(WP_CONTENT_DIR . 'build');
	}

	// Probar archivo inexistente
    public function test_archivo_inexistente()
	{
		// Expectativas
		self::expectOutputRegex("/<div class=[\"']scsslib-error[\"']>/");

		// Ejecutar
		$stub = ScssLibrary::get_instance();
		$file_scss = WP_CONTENT_URL . 'inexistente.scss'; // Usar archivo inexistente
		$file_css = $stub->style_loader_src($file_scss, 'test');
		$stub->wp_footer();

		// Borrar archivos compilados
		self::recurseRmdir(WP_CONTENT_DIR . 'build');
	}

	// Probar con problemas al crear el directorio
	public function test_problemas_creacion_directorio()
	{
		// Expectativas
		self::expectOutputRegex("/<div class=[\"']scsslib-error[\"']>/");
		self::expectOutputRegex("/File Permissions Error, unable to create cache directory./");
		// Simulación: directorio no se pudo crear
		Functions\when('wp_mkdir_p')->justReturn(false);

		// Ejecutar
		$stub = ScssLibrary::get_instance();
		$file_scss = WP_CONTENT_URL . 'style.scss'; // Usar archivo real
		$file_css = $stub->style_loader_src($file_scss, 'test');
		$stub->wp_footer();
	}

	// // Probar con problemas de escritura en el directorio
	// public function test_problemas_escritura_directorio()
	// {
	// 	// Expectativas
	// 	self::expectOutputRegex("/<div class=[\"']scsslib-error[\"']>/");
	// 	self::expectOutputRegex("/File Permissions Error, permission denied./");
	// 	// Simulación: Objeto a ser mimetizado
	// 	// $wp_fs_mock = Mockery::mock( 'WP_Filesystem_Direct' );
	// 	// $wp_fs_mock
	// 	// 	->shouldReceive( 'is_writable' )
	// 	// 	->once()
	// 	// 	->andReturn(false);
	//
	// 	// Inicializar clase
	// 	$stub = ScssLibrary::get_instance();
	// 	$stub_class = get_class($stub);
	//
	// 	$file_scss = WP_CONTENT_URL . 'style.scss';
	// 	$file_css = $stub->style_loader_src($file_scss, 'test');
	// 	$stub->wp_footer();
	// }

	// Generar un error en la compilación de un archivo
	public function test_problemas_al_compilar()
	{
		// Expectativas
		self::expectOutputRegex("/<div class=[\"']scsslib-error[\"']>/");

		// Ejecutar
		$stub = ScssLibrary::get_instance();
		$file_scss = WP_CONTENT_URL . 'error.scss'; // Usar archivo con error
		$file_css = $stub->style_loader_src($file_scss, 'test');
		$stub->wp_footer();
	}

	// Forzar compilación cuando variables no coinciden
	public function test_archivo_reciente()
	{
		// Ejecutar primera pasada para obtener archivo
		$stub = ScssLibrary::get_instance();
		$file_scss = WP_CONTENT_URL . 'style.scss'; // Usar archivo bien formado
		$file_css = $stub->style_loader_src($file_scss, 'test');
		$file_css = str_replace('file://', '', $file_css);

		// Simular arreglo de filetime con un archivo ya creado
		// Esto hará también que las variables no coincidan
		Functions\when('get_transient')->justReturn(array(
			$file_css => 99999999999
		));

		// Ejecutar nuevamente con la simulación
		$file_css = $stub->style_loader_src($file_scss, 'test');

		// ¿Se creó la instancia de clase ScssLibrary\ScssLibrary?
		self::assertInstanceOf('ScssLibrary\ScssLibrary', $stub);
	}
}
