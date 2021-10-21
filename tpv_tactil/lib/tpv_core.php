<?php
/**
 * @author Carlos García Gómez      neorazorx@gmail.com
 * @copyright 2015-2018, Carlos García Gómez. All Rights Reserved. 
 * @copyright 2015-2018, Jorge Casal Lopez. All Rights Reserved.
 */
require_once 'plugins/facturacion_base/extras/fbase_controller.php';
require_once 'extras/phpmailer/class.phpmailer.php';
require_once 'extras/phpmailer/class.smtp.php';

/**
 * Description of tpv_core
 *
 * @author carlos
 */
class tpv_core extends fbase_controller
{

    /**
     *
     * @var agente
     */
    public $agente;

    /**
     *
     * @var almacen
     */
    public $almacen;

    /**
     *
     * @var tpv_arqueo|bool
     */
    public $arqueo;

    /**
     *
     * @var string
     */
    public $articulos_grid;

    /**
     *
     * @var string
     */
    public $busqueda;

    /**
     *
     * @var cliente
     */
    public $cliente;

    /**
     *
     * @var cliente
     */
    public $cliente_s;

    /**
     *
     * @var tpv_comanda
     */
    public $comanda;

    /**
     *
     * @var fabricante
     */
    public $fabricante;

    /**
     *
     * @var forma_pago
     */
    public $forma_pago;

    /**
     *
     * @var array
     */
    public $historial;

    /**
     *
     * @var impuesto
     */
    public $impuesto;

    /**
     *
     * @var tpv_movimiento
     */
    public $movimiento;

    /**
     *
     * @var int
     */
    public $numlineas;

    /**
     *
     * @var array
     */
    public $resultado;

    /**
     *
     * @var serie
     */
    public $serie;

    /**
     *
     * @var terminal_caja
     */
    public $terminal;

    /**
     *
     * @var bool
     */
    public $tesoreria;

    /**
     *
     * @var array
     */
    public $tpv_config;

    /**
     *
     * @var float
     */
    public $utlcambio;

    /**
     *
     * @var float
     */
    public $ultentregado;

    /**
     *
     * @var float
     */
    public $ultventa;

    public function __construct()
    {
        parent::__construct('tpv_tactil', 'TPV Táctil', 'TPV');
    }

    protected function add_ref()
    {
        $this->template = 'ajax/tpv_tactil_lineas';
        $this->resultado = [];

        $art0 = new articulo();
        $articulo = $art0->get($_REQUEST['add_ref']);
        if ($articulo) {
            $this->resultado[] = $articulo;
            $this->precios_resultados($this->resultado);

            if (isset($_POST['desc'])) {
                $this->resultado[0]->descripcion = base64_decode($_POST['desc']);
                $this->resultado[0]->pvp = floatval($_POST['pvp']);
                $this->resultado[0]->dtopor = floatval($_POST['dto']);
            }

            if (isset($_POST['codcombinacion'])) {
                $this->resultado[0]->codcombinacion = $_POST['codcombinacion'];
            }

            $this->numlineas = isset($_POST['numlineas']) ? (int) $_POST['numlineas'] : 0;
        }
    }

    protected function buscar_articulo()
    {
        $this->resultado = [];

        $articulo = new articulo();
        $artcod = new articulo_codbarras();
        foreach ($artcod->search($_REQUEST['codbar2']) as $ac) {
            $this->resultado[] = $articulo->get($ac->referencia);
            break;
        }

        if (empty($this->resultado)) {
            /// buscamos por código de barras de la combinación
            $combi0 = new articulo_combinacion();
            foreach ($combi0->search($this->query) as $combi) {
                $art = $articulo->get($combi->referencia);
                if ($art && $combi->codbarras == $_REQUEST['codbar2']) {
                    $art->codbarras = $combi->codbarras;
                    $this->resultado[] = $art;
                }
            }

            foreach ($articulo->search_by_codbar($_REQUEST['codbar2']) as $ac) {
                $this->resultado[] = $articulo->get($ac->referencia);
                break;
            }
        }

        $this->precios_resultados($this->resultado);

        $this->numlineas = isset($_POST['numlineas']) ? (int) $_POST['numlineas'] : 0;
        if ($this->resultado) {
            if ($this->resultado[0]->tipo) {
                $this->template = FALSE;
                echo "get_combinaciones('" . $this->resultado[0]->referencia . "')";
            } else {
                $this->template = 'ajax/tpv_tactil_lineas';
            }
        } else {
            $this->template = FALSE;
            echo '<!--no_encontrado-->';
        }
    }

    protected function get_articulos_familia()
    {
        $this->template = 'ajax/tpv_tactil_codfamilia';

        $familia = new familia();
        $fam = $familia->get($_REQUEST['codfamilia']);
        if ($fam) {
            foreach ($fam->get_articulos(0, 150) as $art) {
                if (!$art->bloqueado) {
                    $this->resultado[] = $art;
                }
            }
            $this->precios_resultados($this->resultado);
        }
    }

    protected function get_combinaciones_articulo()
    {
        /// cambiamos la plantilla HTML
        $this->template = 'ajax/tpv_tactil_combinaciones';

        $this->results = [];

        /// obtenemos el artículo
        $art0 = new articulo();
        $articulo = $art0->get($_POST['referencia4combi']);
        if ($articulo) {
            /// usamos precios_resultados para obtener el precio de tarifa
            $aux = array($articulo);
            $this->precios_resultados($aux);

            $comb1 = new articulo_combinacion();
            foreach ($comb1->all_from_ref($_POST['referencia4combi']) as $com) {
                if (isset($this->results[$com->codigo])) {
                    $this->results[$com->codigo]['desc'] .= ', ' . $com->nombreatributo . ' - ' . $com->valor;
                    $this->results[$com->codigo]['txt'] .= ', ' . $com->nombreatributo . ' - ' . $com->valor;
                    continue;
                }

                $this->results[$com->codigo] = array(
                    'ref' => $_POST['referencia4combi'],
                    'desc' => $aux[0]->descripcion . " | " . $com->nombreatributo . ' - ' . $com->valor,
                    'pvp' => floatval($aux[0]->pvp) + $com->impactoprecio,
                    'dto' => floatval($aux[0]->dtopor),
                    'codbarras' => $com->codbarras,
                    'codimpuesto' => $aux[0]->codimpuesto,
                    'iva' => $aux[0]->get_iva(),
                    'cantidad' => 1,
                    'txt' => $com->nombreatributo . ' - ' . $com->valor,
                    'codigo' => $com->codigo,
                    'stockfis' => $com->stockfis,
                );
            }
        }
    }

    private function get_subcuenta_pago($codcuentabanco, $codejercicio)
    {
        if (!empty($codcuentabanco)) {
            $cuenta_banco_model = new cuenta_banco();
            $cbanco = $cuenta_banco_model->get($codcuentabanco);
            if ($cbanco) {
                return $cbanco->codsubcuenta;
            }
        }

        $cuenta_model = new cuenta();
        $c_caja = $cuenta_model->get_cuentaesp('CAJA', $codejercicio);
        if ($c_caja) {
            foreach ($c_caja->get_subcuentas() as $subc) {
                return $subc->codsubcuenta;
            }
        }

        return false;
    }

    /**
     * 
     * @param factura_cliente $factura
     * @param tpv_comanda     $comanda
     */
    protected function generar_recibos($factura, $comanda)
    {
        $ref0 = new recibo_factura();

        $formap = $this->forma_pago->get($comanda->codpago);
        if ($formap && $comanda->totalpago != 0) {
            $recibo = new recibo_cliente();
            $recibo->cifnif = $factura->cifnif;
            $recibo->coddivisa = $factura->coddivisa;
            $recibo->tasaconv = $factura->tasaconv;
            $recibo->codcliente = $factura->codcliente;
            $recibo->estado = 'Pagado';
            $recibo->fecha = $factura->fecha;
            $recibo->fechav = $factura->fecha;
            $recibo->idfactura = $factura->idfactura;
            $recibo->importe = $comanda->totalpago;
            $recibo->codpago = $formap->codpago;
            $recibo->nombrecliente = $factura->nombrecliente;
            $recibo->numero = $recibo->new_numero($recibo->idfactura);
            $recibo->codigo = $factura->codigo . '-' . sprintf('%02s', $recibo->numero);
            if ($recibo->save()) {
                $codsubcuenta = $this->get_subcuenta_pago($formap->codcuenta, $factura->codejercicio);
                $ref0->nuevo_pago_cli($recibo, $codsubcuenta);
            }
        }

        $formap = $this->forma_pago->get($comanda->codpago2);
        if ($formap && $comanda->totalpago2 != 0) {
            $recibo = new recibo_cliente();
            $recibo->cifnif = $factura->cifnif;
            $recibo->coddivisa = $factura->coddivisa;
            $recibo->tasaconv = $factura->tasaconv;
            $recibo->codcliente = $factura->codcliente;
            $recibo->estado = 'Pagado';
            $recibo->fecha = $factura->fecha;
            $recibo->fechav = $factura->fecha;
            $recibo->idfactura = $factura->idfactura;
            $recibo->importe = $comanda->totalpago2;
            $recibo->codpago = $formap->codpago;
            $recibo->nombrecliente = $factura->nombrecliente;
            $recibo->numero = $recibo->new_numero($recibo->idfactura);
            $recibo->codigo = $factura->codigo . '-' . sprintf('%02s', $recibo->numero);
            if ($recibo->save()) {
                $codsubcuenta = $this->get_subcuenta_pago($formap->codcuenta, $factura->codejercicio);
                $ref0->nuevo_pago_cli($recibo, $codsubcuenta);
            }
        }
    }

    private function load_config()
    {
        $fsvar = new fs_var();
        $this->tpv_config = array(
            'tpv_ref_varios' => '',
            'tpv_linea_libre' => 1,
            'tpv_familias' => false,
            'tpv_volver_familias' => false,
            'tpv_fpago_efectivo' => false,
            'tpv_fpago_tarjeta' => false,
            'tpv_texto_fin' => '',
            'tpv_preimprimir' => false,
            'tpv_emails_z' => '',
        );
        $this->tpv_config = $fsvar->array_get($this->tpv_config, false);
        $this->articulos_grid = isset($_COOKIE['tpv_tactil_articulos_grid']) ? $_COOKIE['tpv_tactil_articulos_grid'] : '6x3';
    }

    protected function new_search()
    {
        /// desactivamos la plantilla HTML
        $this->template = false;
        $articulo = new articulo();

        $codfamilia = isset($_REQUEST['codfamilia']) ? $_REQUEST['codfamilia'] : '';
        $codfabricante = isset($_REQUEST['codfabricante']) ? $_REQUEST['codfabricante'] : '';
        $con_stock = isset($_REQUEST['con_stock']);
        $resultados = $articulo->search($this->query, 0, $codfamilia, $con_stock, $codfabricante);

        $this->precios_resultados($resultados);

        header('Content-Type: application/json');
        echo json_encode($resultados);
    }

    private function precios_resultados(&$res)
    {
        if ($this->agente) {
            $arqueo = new tpv_arqueo();
            $terminal0 = new terminal_caja();
            foreach ($arqueo->all_by_agente($this->agente->codagente) as $aq) {
                if ($aq->abierta) {
                    $this->arqueo = $aq;
                    $this->terminal = $terminal0->get($aq->idterminal);
                    break;
                }
            }
        }

        if ($this->terminal) {
            $serie = $this->serie->get($this->terminal->codserie);
            $stock = new stock();
        }

        foreach ($res as $i => $value) {
            $res[$i]->query = $this->query;
            $res[$i]->dtopor = 0;
            $res[$i]->cantidad = 1;

            $res[$i]->stockalm = $value->stockfis;
            if ($this->terminal) {
                if ($this->multi_almacen) {
                    $res[$i]->stockalm = $stock->total_from_articulo($value->referencia, $this->terminal->codalmacen);
                }

                if ($serie->siniva) {
                    $res[$i]->codimpuesto = NULL;
                }
            }
        }

        if (isset($_REQUEST['codcliente'])) {
            $cliente = $this->cliente->get($_REQUEST['codcliente']);
            $tarifa0 = new tarifa();

            if ($cliente) {
                if ($cliente->regimeniva == 'Exento') {
                    foreach ($res as $i => $value) {
                        $res[$i]->codimpuesto = NULL;
                    }
                }

                if ($cliente->codtarifa) {
                    $tarifa = $tarifa0->get($cliente->codtarifa);
                    if ($tarifa) {
                        $tarifa->set_precios($res);
                    }
                } else if ($cliente->codgrupo) {
                    $grupo0 = new grupo_clientes();

                    $grupo = $grupo0->get($cliente->codgrupo);
                    if ($grupo) {
                        $tarifa = $tarifa0->get($grupo->codtarifa);
                        if ($tarifa) {
                            $tarifa->set_precios($res);
                        }
                    }
                }

                /// aplicamos el descuento al precio
                foreach ($res as $i => $value) {
                    if ($value->dtopor != 0) {
                        $res[$i]->pvp -= $value->pvp * $value->dtopor / 100;
                    }
                }
            }
        }
    }

    protected function private_core()
    {
        parent::private_core();
        $this->share_extensions();

        $this->agente = $this->user->get_agente();
        $this->almacen = new almacen();
        $this->arqueo = false;
        $this->busqueda = '';
        $this->cliente = new cliente();
        $this->cliente_s = false;
        $this->comanda = false;
        $this->fabricante = new fabricante();
        $this->forma_pago = new forma_pago();
        $this->historial = [];
        $this->impuesto = new impuesto();
        $this->movimiento = false;
        $this->numlineas = 0;
        $this->resultado = [];
        $this->serie = new serie();
        $this->terminal = false;
        $this->tesoreria = class_exists('recibo_cliente');
        $this->utlcambio = 0;
        $this->ultentregado = 0;
        $this->ultventa = 0;

        $this->load_config();
    }

    private function share_extensions()
    {
        $fsext = new fs_extension();
        $fsext->name = 'api_remote_printer';
        $fsext->from = $this->page->name;
        $fsext->type = 'api';
        $fsext->text = 'remote_printer';
        $fsext->save();
    }
}
