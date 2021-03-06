<?php
/**
 * @author Carlos García Gómez      neorazorx@gmail.com
 * @copyright 2015-2018, Carlos García Gómez. All Rights Reserved. 
 * @copyright 2015-2018, Jorge Casal Lopez. All Rights Reserved.
 */
require_once __DIR__ . '/../lib/tpv_core.php';

class tpv_tactil extends tpv_core
{

    protected function private_core()
    {
        parent::private_core();

        if (isset($_REQUEST['buscar_cliente'])) {
            $this->fbase_buscar_cliente($_REQUEST['buscar_cliente']);
        } else if (isset($_REQUEST['codbar2'])) {
            $this->buscar_articulo();
        } else if ($this->query != '') {
            $this->new_search();
        } else if (isset($_REQUEST['codfamilia'])) {
            $this->get_articulos_familia();
        } else if (isset($_POST['referencia4combi'])) {
            $this->get_combinaciones_articulo();
        } else if (isset($_REQUEST['get_factura'])) {
            $this->get_factura();
        } else if (isset($_REQUEST['add_ref'])) {
            $this->add_ref();
        } else if ($this->agente) {
            $arqueo = new tpv_arqueo();
            $terminal0 = new terminal_caja();
            foreach ($arqueo->all_by_agente($this->agente->codagente) as $aq) {
                if ($aq->abierta) {
                    $this->arqueo = $aq;
                    $this->terminal = $terminal0->get($aq->idterminal);
                    break;
                }
            }

            if (!$this->arqueo) {
                if (isset($_POST['terminal'])) {
                    $this->terminal = $terminal0->get($_POST['terminal']);
                    if (!$this->terminal) {
                        $this->new_error_msg('Terminal no encontrado.');
                    } else if ($this->terminal->disponible()) {
                        $this->arqueo = new tpv_arqueo();
                        $this->arqueo->idterminal = $this->terminal->id;
                        $this->arqueo->codagente = $this->agente->codagente;
                        $this->arqueo->inicio = floatval($_POST['d_inicial']);
                        $this->arqueo->totalcaja = floatval($_POST['d_inicial']);

                        if ($this->arqueo->save()) {
                            $this->new_message("Arqueo iniciado con " . $this->show_precio($this->arqueo->inicio));
                        } else {
                            $this->new_error_msg("¡Imposible guardar los datos del arqueo!");
                        }
                    } else {
                        $this->new_error_msg('El terminal ya no está disponible.');
                    }
                } else if (isset($_GET['terminal'])) {
                    $this->terminal = $terminal0->get($_GET['terminal']);
                    if ($this->terminal) {
                        $this->terminal->abrir_cajon();
                        $this->terminal->save();
                    } else {
                        $this->new_error_msg('Terminal no encontrado.');
                    }
                }
            }

            if ($this->arqueo) {
                if (isset($_POST['cliente'])) {
                    $this->cliente_s = $this->cliente->get($_POST['cliente']);
                } else if ($this->terminal) {
                    $this->cliente_s = $this->cliente->get($this->terminal->codcliente);
                }

                if (!$this->cliente_s) {
                    foreach ($this->cliente->all() as $cli) {
                        $this->cliente_s = $cli;
                        break;
                    }
                }

                if ($this->cliente_s) {
                    $this->caja_iniciada();
                } else {
                    $this->new_error_msg('No hay ningún cliente. Crea uno, por ejemplo <b>Contado</b>.');
                }
            } else {
                $this->results = $terminal0->disponibles();
            }
        } else {
            $this->new_error_msg('No tienes un <a href="' . $this->user->url() . '">agente asociado</a>
            a tu usuario, y por tanto no puedes hacer tickets.');
        }
    }

    private function caja_iniciada()
    {
        $this->template = 'tpv_tactil2';
        $tpvcom = new tpv_comanda();

        if (isset($_POST['idtpv_comanda'])) {
            /**
             * El ticket estaba aparcado y ahora lo cargamos para finalizar
             */
            $this->comanda = $tpvcom->get($_POST['idtpv_comanda']);
            if ($this->comanda) {
                $this->cliente_s = $this->cliente->get($this->comanda->codcliente);
                $this->ultentregado = $this->comanda->ultentregado;
            }
        }

        if (isset($_REQUEST['delete_comanda'])) {
            $comanda = $tpvcom->get($_REQUEST['delete_comanda']);
            if ($comanda && $comanda->delete()) {
                $this->new_message('Ticket eliminado correctamente.');
            }

            if ($this->terminal->forzar_pin) {
                $this->template = 'tpv_tactil_pin';
            }
        } else if (isset($_GET['idtpv_comanda'])) {
            /// des-aparcamos un ticket
            $this->comanda = $tpvcom->get($_GET['idtpv_comanda']);
            if ($this->comanda) {
                $this->cliente_s = $this->cliente->get($this->comanda->codcliente);
                $this->ultentregado = $this->comanda->ultentregado;
            }
        } else if (isset($_GET['articulos_grid'])) {
            $this->articulos_grid = $_GET['articulos_grid'];
            setcookie('tpv_tactil_articulos_grid', $this->articulos_grid, time() + FS_COOKIES_EXPIRE);
        } else if (isset($_GET['abrir_caja'])) {
            $this->abrir_caja();
        } else if (isset($_GET['cerrando'])) {
            $this->template = 'tpv_tactil_cierre';
            $this->terminal->abrir_cajon();
            $this->terminal->save();
        } else if (isset($_POST['cerrar_caja'])) {
            $this->cerrar_caja();
        } else if (isset($_POST['idfactura'])) {
            /// modificar una factura
            $this->modificar_factura();
        } else if (isset($_POST['cliente'])) {
            if ($this->comanda) {
                $this->comanda->delete();
                $this->comanda = FALSE;
            }

            $this->guardar_ticket();

            if ($this->terminal->forzar_pin) {
                $this->template = 'tpv_tactil_pin';
            }
        } else if (isset($_GET['imprimir'])) {
            $fact0 = new factura_cliente();
            $factura = $fact0->get($_GET['imprimir']);
            if ($factura) {
                $this->imprimir_ticket($factura);
            }
        } else if (isset($_REQUEST['cambio_agente'])) {
            $this->cambiar_agente($_REQUEST['cambio_agente']);
        } else if (isset($_REQUEST['rfid_agente'])) {
            $this->cambiar_agente_rfid($_REQUEST['rfid_agente']);
        } else if ($this->terminal->forzar_pin) {
            $this->template = 'tpv_tactil_pin';
        } else if (isset($_GET['cierre_x'])) {
            $this->cierre_x();
        }

        $comanda = new tpv_comanda();
        $this->historial = $comanda->all_from_arqueo($this->arqueo->idtpv_arqueo);
        foreach ($this->historial as $h) {
            $this->utlcambio = $h->ultcambio;
            $this->ultentregado = $h->ultentregado;
            $this->ultventa = $h->total;
            break;
        }
    }

    private function cambiar_agente()
    {
        $agente = $this->agente->get($_REQUEST['cambio_agente']);
        if ($agente) {
            if ($this->agente_en_otro_terminal($agente->codagente)) {
                $this->new_error_msg('Empleado ya asignado a otro terminal abierto.');
            } else if ($agente->pin) {
                if (isset($_REQUEST['pin'])) {
                    if ($agente->pin == $_REQUEST['pin']) {
                        $this->agente = $agente;
                        $this->user->codagente = $this->agente->codagente;
                        $this->user->save();

                        $this->arqueo->codagente = $this->agente->codagente;
                        $this->arqueo->agente = $this->agente;
                        $this->arqueo->save();
                    } else {
                        $this->new_error_msg('PIN incorrecto.');
                        $this->template = 'tpv_tactil_pin';
                    }
                }
            } else {
                $this->agente = $agente;
                $this->user->codagente = $this->agente->codagente;
                $this->user->save();

                $this->arqueo->codagente = $this->agente->codagente;
                $this->arqueo->agente = $this->agente;
                $this->arqueo->save();
            }
        } else {
            $this->new_error_msg('Empleado no encontrado.');
            $this->template = 'tpv_tactil_pin';
        }
    }

    private function cambiar_agente_rfid()
    {
        $agente = $this->agente->get_by_rfid($_REQUEST['rfid_agente']);
        if ($agente) {
            if ($this->agente_en_otro_terminal($agente->codagente)) {
                $this->new_error_msg('Empleado ya asignado a otro terminal abierto.');
            } else {
                $this->agente = $agente;
                $this->user->codagente = $this->agente->codagente;
                $this->user->save();

                $this->arqueo->codagente = $this->agente->codagente;
                $this->arqueo->agente = $this->agente;
                $this->arqueo->save();
            }
        } else {
            $this->new_error_msg('Empleado no encontrado.');
            $this->template = 'tpv_tactil_pin';
        }
    }

    private function agente_en_otro_terminal($codagente)
    {
        foreach ($this->arqueo->all_by_agente($codagente) as $arq) {
            if ($arq->abierta) {
                return true;
            }
        }

        return false;
    }

    private function abrir_caja()
    {
        if (isset($_POST['cantidad'])) {
            $mov = new tpv_movimiento();
            $mov->idtpv_arqueo = $this->arqueo->idtpv_arqueo;
            $mov->codagente = $this->user->codagente;
            $mov->descripcion = $_POST['descripcion'];

            if ($_POST['movimiento'] == 'entrada') {
                $mov->cantidad = floatval($_POST['cantidad']);
            } else {
                $mov->cantidad = 0 - floatval($_POST['cantidad']);
            }

            if ($mov->save()) {
                $this->new_message('Movimiento guardado correctamente.');
                $this->arqueo->totalmov += $mov->cantidad;
                $this->arqueo->totalcaja += $mov->cantidad;
                $this->arqueo->save();
            } else {
                $this->new_error_msg('Error al guardar el movimiento de caja.');
            }
        } else {
            $this->movimiento = new tpv_movimiento();
            $this->terminal->abrir_cajon();
        }

        $this->terminal->save();
    }

    private function cierre_x()
    {
        $this->imprimir_cierre('CIERRE X');
        $this->new_message('Imprimiendo cierre X...');
    }

    private function cerrar_caja()
    {
        $this->arqueo->abierta = FALSE;
        $this->arqueo->diahasta = Date('d-m-Y');

        $fields = [
            'm001', 'm002', 'm005', 'm010', 'm020', 'm050', 'm1', 'm2', 'm50',
            'm100', 'm200', 'm500', 'm1000', 'b5', 'b10', 'b20', 'b50',
            'b100', 'b200', 'b500', 'b1000', 'b2000', 'b5000', 'b10000'
        ];
        foreach ($fields as $field) {
            $this->arqueo->{$field} = isset($_POST[$field]) ? (float) $_POST[$field] : 0;
        }

        if ($this->arqueo->save()) {
            $this->imprimir_cierre();

            /// ¿Enviamos el cierre por email?
            if ($this->empresa->can_send_mail() && $this->tpv_config['tpv_emails_z']) {
                $this->enviar_email_cierre($this->tpv_config['tpv_emails_z'], $this->terminal->tickets);
            }

            /// recargamos la página
            header('location: ' . $this->url());
        } else {
            $this->new_error_msg("¡Imposible cerrar la caja!");
        }
    }

    private function imprimir_cierre($titulo = 'ZETA / CIERRE DE CAJA')
    {
        $this->terminal->abrir_cajon();
        $this->terminal->add_linea("\n== " . $titulo . " ==\n");
        $this->terminal->add_linea($this->empresa->nombrecorto . ' ' . $this->terminal->nombre . "\n");
        $this->terminal->add_linea("Cierre de caja: " . $this->arqueo->idtpv_arqueo . "\n");
        $this->terminal->add_linea("Realizado: " . $this->today() . ' ' . $this->hour() . "\n");
        $this->terminal->add_linea("Cajero/a: " . $this->agente->get_fullname() . "\n");

        $this->terminal->add_linea("\n== DINERO EN CAJA ==\n");
        $this->terminal->add_linea("Apertura: " . $this->arqueo->diadesde . "\n");
        $this->terminal->add_linea("Inicial: " . $this->show_precio($this->arqueo->inicio, FALSE, FALSE) . "\n");
        $this->terminal->add_linea("Ventas en efectivo: " . $this->show_precio($this->arqueo->totalcaja - $this->arqueo->inicio, FALSE, FALSE) . "\n");
        $this->terminal->add_linea("------------------------\n");
        $this->terminal->add_linea("Efectivo en caja: " . $this->show_precio($this->arqueo->totalcaja, FALSE, FALSE) . "\n");

        $this->terminal->add_linea("\n== VENTAS ==\n");
        $this->terminal->add_linea("Operaciones: " . $this->arqueo->num_tickets() . "\n");
        $this->terminal->add_linea("Articulos: " . $this->arqueo->num_articulos() . "\n");
        $this->terminal->add_linea("Unidades: " . $this->arqueo->count_articulos() . "\n");
        $this->terminal->add_linea("Pagos en efectivo: " . $this->show_precio($this->arqueo->totalcaja - $this->arqueo->inicio, FALSE, FALSE) . "\n");
        $this->terminal->add_linea("Pagos con tarjeta: " . $this->show_precio($this->arqueo->totaltarjeta, FALSE, FALSE) . "\n");
        $this->terminal->add_linea("------------------------\n");
        $this->terminal->add_linea(
            "Total ventas: " .
            $this->show_precio($this->arqueo->totalcaja - $this->arqueo->inicio + $this->arqueo->totaltarjeta, FALSE, FALSE) . "\n"
        );

        $this->terminal->add_linea("\n== ARQUEO DE CAJA ==\n");
        $this->terminal->add_linea("Efectivo en caja teorico: " . $this->show_precio($this->arqueo->totalcaja, FALSE, FALSE) . "\n");
        $this->terminal->add_linea("Efectivo contado: " . $this->show_precio($this->arqueo->total_contado(), FALSE, FALSE) . "\n");

        $diferencia = $this->arqueo->total_contado() - $this->arqueo->totalcaja;
        $this->terminal->add_linea("Diferencia: " . $this->show_precio($diferencia, FALSE, FALSE) . "\n");

        $this->terminal->add_linea("\n== VENTAS POR FAMILIA ==\n");
        foreach ($this->arqueo->desglose_familias() as $fam) {
            $this->terminal->add_linea($fam['nombre'] . ' ' . $this->show_numero($fam['cantidad']) . "\n");
        }

        $this->terminal->add_linea("\n\n\n\n\n\n");
        $this->terminal->cortar_papel();
        $this->terminal->save();
    }

    private function enviar_email_cierre($emails, $txt)
    {
        $email_list = explode(',', $emails);
        foreach ($email_list as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail = $this->empresa->new_mail();
                $mail->FromName = $this->user->get_agente_fullname();

                $mail->addAddress($email);
                $mail->Subject = $this->empresa->nombre . ': Cierre de arqueo #' . $this->arqueo->idtpv_arqueo;
                $mail->msgHTML(nl2br($txt));

                if ($this->empresa->mail_connect($mail) && $mail->send()) {
                    $this->new_message('Mensaje enviado correctamente.');
                    $this->empresa->save_mail($mail);
                } else {
                    $this->new_error_msg("Error al enviar el email: " . $mail->ErrorInfo);
                }
            }
        }
    }

    private function get_factura()
    {
        $this->template = 'ajax/tpv_tactil_factura';
        $fact0 = new factura_cliente();
        $this->resultado = $fact0->get($_REQUEST['get_factura']);
    }

    private function guardar_ticket()
    {
        $continuar = TRUE;

        $ej0 = new ejercicio();
        $ejercicio = $ej0->get_by_fecha($_POST['fecha']);
        if (!$ejercicio) {
            $this->new_error_msg('Ejercicio no encontrado.');
            $continuar = FALSE;
        }

        $serie0 = new serie();
        $serie = $serie0->get($_POST['serie']);
        if (!$serie) {
            $this->new_error_msg('Serie no encontrada.');
            $continuar = FALSE;
        }

        $forma_pago = $this->forma_pago->get($this->tpv_config['tpv_fpago_efectivo']);
        $forma_pago2 = FALSE;
        if (isset($_POST['tpv_tarjeta']) && floatval($_POST['tpv_tarjeta']) > 0) {
            if (floatval($_POST['tpv_efectivo']) > 0) {
                $forma_pago2 = $this->forma_pago->get($this->tpv_config['tpv_fpago_tarjeta']);
            } else {
                $forma_pago = $this->forma_pago->get($this->tpv_config['tpv_fpago_tarjeta']);
            }
        }

        if (!$forma_pago) {
            $this->new_error_msg('Forma de pago no encontrada.');
            $continuar = FALSE;
        }

        $div0 = new divisa();
        $divisa = $div0->get($this->empresa->coddivisa);
        if (!$divisa) {
            $this->new_error_msg('Divisa no encontrada.');
            $continuar = FALSE;
        }

        $cliente = $this->cliente->get($_POST['cliente']);
        if (!$cliente) {
            $this->new_error_msg('Cliente no encontrado.');
            $continuar = FALSE;
        }

        $comanda = new tpv_comanda();

        if ($this->duplicated_petition($_POST['petition_id'])) {
            $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón Guardar
               y se han enviado dos peticiones.');
            $continuar = FALSE;
        }

        if ($continuar) {
            $comanda->idtpv_arqueo = $this->arqueo->idtpv_arqueo;
            $comanda->fecha = $_POST['fecha'];
            $comanda->codalmacen = $this->terminal->codalmacen;

            $comanda->codpago = $forma_pago->codpago;
            if ($forma_pago2) {
                $comanda->codpago2 = $forma_pago2->codpago;
            }

            if (floatval($_POST['tpv_efectivo']) > 0) {
                $comanda->totalpago = floatval($_POST['tpv_efectivo']);
                if (isset($_POST['tpv_tarjeta']) && floatval($_POST['tpv_tarjeta']) > 0) {
                    $comanda->totalpago2 = floatval($_POST['tpv_tarjeta']);
                }
            } else if (isset($_POST['tpv_tarjeta'])) {
                $comanda->totalpago = floatval($_POST['tpv_tarjeta']);
            }

            $comanda->observaciones = $_POST['observaciones'];
            $comanda->numero2 = $_POST['numero2'];
            $comanda->porcomision = $this->agente->porcomision;
            $comanda->ultentregado = floatval($_POST['tpv_efectivo']);
            $comanda->ultcambio = floatval($_POST['tpv_cambio']);
            $comanda->codcliente = $cliente->codcliente;
            $comanda->cifnif = $cliente->cifnif;
            $comanda->nombrecliente = $cliente->razonsocial;
            foreach ($cliente->get_direcciones() as $d) {
                if ($d->domfacturacion) {
                    $comanda->ciudad = $d->ciudad;
                    $comanda->coddir = $d->id;
                    $comanda->codpais = $d->codpais;
                    $comanda->codpostal = $d->codpostal;
                    $comanda->direccion = $d->direccion;
                    $comanda->provincia = $d->provincia;
                    break;
                }
            }

            if (is_null($comanda->codcliente)) {
                $this->new_error_msg("No hay ninguna dirección asociada al cliente.");
            } else if ($comanda->save()) {
                $articulo = new articulo();

                $n = floatval($_POST['numlineas']);
                for ($i = 0; $i < $n; $i++) {
                    if (isset($_POST['referencia_' . $i])) {
                        $art0 = $articulo->get($_POST['referencia_' . $i]);
                        if ($art0) {
                            $linea = new linea_comanda();
                            $linea->idtpv_comanda = $comanda->idtpv_comanda;
                            $linea->referencia = $art0->referencia;
                            $linea->descripcion = $_POST['desc_' . $i];
                            $linea->codimpuesto = $art0->codimpuesto;
                            $linea->iva = floatval($_POST['iva_' . $i]);
                            $linea->pvpunitario = floatval($_POST['pvp_' . $i]);
                            $linea->cantidad = floatval($_POST['cantidad_' . $i]);
                            $linea->pvpsindto = $linea->pvpunitario * $linea->cantidad;
                            $linea->pvptotal = $linea->pvpunitario * $linea->cantidad;

                            if ($_POST['codcombinacion_' . $i]) {
                                $linea->codcombinacion = $_POST['codcombinacion_' . $i];
                            }

                            if ($linea->save()) {
                                /// descontamos del stock
                                $art0->sum_stock($comanda->codalmacen, 0 - $linea->cantidad, FALSE, $linea->codcombinacion);

                                $comanda->neto += $linea->pvptotal;
                                $comanda->totaliva += ($linea->pvptotal * $linea->iva / 100);
                            } else {
                                $this->new_error_msg("¡Imposible guardar la línea con referencia: " . $linea->referencia);
                                $continuar = FALSE;
                            }
                        } else {
                            $this->new_error_msg("Artículo no encontrado: " . $_POST['referencia_' . $i]);
                            $continuar = FALSE;
                        }
                    }
                }

                if ($continuar) {
                    /// redondeamos
                    $comanda->neto = round($comanda->neto, FS_NF0);
                    $comanda->totaliva = round($comanda->totaliva, FS_NF0);
                    $comanda->total = $comanda->neto + $comanda->totaliva;

                    if ($comanda->totalpago > $comanda->total) {
                        $comanda->totalpago = $comanda->total;
                    }

                    if (abs(floatval($_POST['tpv_total']) - $comanda->total) >= .02) {
                        $this->new_error_msg("El total difiere entre la vista y el controlador (" . $_POST['tpv_total'] .
                            " frente a " . $comanda->total . "). Debes informar del error.");
                        $comanda->delete();
                    } else if ($comanda->save()) {
                        if ($_POST['aparcar'] == 'FALSE') {
                            $this->generar_factura($comanda, $ejercicio, $serie, $divisa, $forma_pago, $cliente);
                        } else if ($_POST['preimprimir'] == 'TRUE') {
                            $this->terminal->preimprimir_ticket_tactil($comanda, $this->empresa);
                            $this->terminal->save();

                            $this->new_message("Ticket aparcado.");
                        } else {
                            $this->new_message("Ticket aparcado.");
                        }
                    } else {
                        $this->new_error_msg("¡Imposible actualizar el ticket!");
                    }
                } else if ($comanda->delete()) {
                    $this->new_message("Ticket eliminado correctamente.");
                } else {
                    $this->new_error_msg("¡Imposible eliminar el ticket!");
                }
            } else {
                $this->new_error_msg("¡Imposible guardar el ticket!");
            }
        }
    }

    /**
     * Añade el ticket a la cola de impresión.
     * @param factura_cliente $factura
     */
    private function imprimir_ticket($factura, $numtickets = 1, $cajon = FALSE)
    {
        if ($cajon) {
            $this->terminal->abrir_cajon();
        }

        while ($numtickets > 0) {
            $efectivo = isset($_POST['tpv_efectivo']) ? $_POST['tpv_efectivo'] : null;
            $tarjeta = isset($_POST['tpv_tarjeta']) ? $_POST['tpv_tarjeta'] : null;
            $cambio = isset($_POST['tpv_cambio']) ? $_POST['tpv_cambio'] : null;
            $this->terminal->imprimir_ticket_tactil($factura, $this->empresa, $efectivo, $tarjeta, $cambio, $this->tpv_config['tpv_texto_fin']);

            if (isset($_POST['regalo'])) {
                $this->terminal->imprimir_ticket_tactil_regalo($factura, $this->empresa, $this->tpv_config['tpv_texto_fin']);
            }

            $numtickets--;
        }

        $this->terminal->save();
        $this->new_message('<a href="#" data-toggle="modal" data-target="#modal_ayuda_ticket">¿No se imprime el ticket?</a>');
    }

    /**
     * 
     * @param tpv_comanda $comanda
     * @param ejercicio $ejercicio
     * @param serie $serie
     * @param divisa $divisa
     */
    private function generar_factura(&$comanda, &$ejercicio, &$serie, &$divisa, &$forma_pago, &$cliente)
    {
        $factura = new factura_cliente();
        $factura->cifnif = $comanda->cifnif;
        $factura->ciudad = $comanda->ciudad;
        $factura->codagente = $this->agente->codagente;
        $factura->codalmacen = $comanda->codalmacen;
        $factura->codcliente = $comanda->codcliente;
        $factura->coddir = $comanda->coddir;
        $factura->coddivisa = $divisa->coddivisa;
        $factura->codejercicio = $ejercicio->codejercicio;
        $factura->codpago = $comanda->codpago;
        $factura->codpais = $comanda->codpais;
        $factura->codpostal = $comanda->codpostal;
        $factura->codserie = $serie->codserie;
        $factura->direccion = $comanda->direccion;
        $factura->neto = $comanda->neto;
        $factura->nombrecliente = $comanda->nombrecliente;
        $factura->observaciones = $comanda->observaciones;
        $factura->porcomision = $this->agente->porcomision;
        $factura->provincia = $comanda->provincia;
        $factura->tasaconv = $divisa->tasaconv;
        $factura->total = $comanda->total;
        $factura->totaliva = $comanda->totaliva;

        $factura->set_fecha_hora($factura->fecha, $factura->hora);
        $factura->vencimiento = $forma_pago->calcular_vencimiento($factura->fecha, $cliente->diaspago);

        /// función auxiliar para implementar en los plugins que lo necesiten
        if (!fs_generar_numero2($factura)) {
            $factura->numero2 = $comanda->numero2;
        }

        if ($factura->save()) {
            $comanda->idfactura = $factura->idfactura;
            $comanda->save();

            foreach ($comanda->get_lineas() as $lin) {
                $linea = new linea_factura_cliente();
                $linea->idfactura = $factura->idfactura;
                $linea->cantidad = $lin->cantidad;
                $linea->codimpuesto = $lin->codimpuesto;
                $linea->descripcion = $lin->descripcion;
                $linea->dtopor = $lin->dtopor;
                $linea->irpf = $lin->irpf;
                $linea->iva = $lin->iva;
                $linea->pvpsindto = $lin->pvpsindto;
                $linea->pvptotal = $lin->pvptotal;
                $linea->pvpunitario = $lin->pvpunitario;
                $linea->recargo = $lin->recargo;
                $linea->referencia = $lin->referencia;
                $linea->codcombinacion = $lin->codcombinacion;
                $linea->save();
            }

            $asiento_factura = new asiento_factura();
            if ($this->tesoreria) {
                $asiento_factura->generar_asiento_venta($factura);
                $this->generar_recibos($factura, $comanda);
            } else {
                $factura->pagada = 0.1 > abs($comanda->totalpago + $comanda->totalpago2 - $factura->total);
                $factura->save();

                if ($this->empresa->contintegrada) {
                    $asiento_factura->generar_asiento_venta($factura);
                }
            }

            $this->arqueo->totalcaja += floatval($_POST['tpv_efectivo']) - floatval($_POST['tpv_cambio']);
            if (isset($_POST['tpv_tarjeta'])) {
                $this->arqueo->totaltarjeta += floatval($_POST['tpv_tarjeta']);
            }
            $this->arqueo->save();

            $this->imprimir_ticket($factura, $this->terminal->num_tickets, TRUE);

            $this->new_message('<a href="' . $factura->url() . '">Factura</a> guardada correctamente.');
        } else {
            $this->new_error_msg('Error al guardar la factura.');
        }
    }

    private function aux_articulos_num()
    {
        $num = 18;
        if ($this->articulos_grid == '4x3') {
            $num = 12;
        } else if ($this->articulos_grid == '3x3') {
            $num = 9;
        }

        return $num;
    }

    public function aux_articulos_tabs()
    {
        $tabs = [];
        $num = $this->aux_articulos_num();

        for ($i = 0; $i * $num <= count($this->resultado); $i++) {
            $tabs[] = $i + 1;
        }

        return $tabs;
    }

    public function aux_articulos_grid()
    {
        $num = $this->aux_articulos_num();
        $articulos = [];

        $num2 = 0;
        foreach ($this->aux_articulos_tabs() as $tab) {
            $arttab = [];
            foreach ($this->resultado as $i => $value) {
                if ($i >= $num2 && $i < $num2 + $num) {
                    $value->funcion_js = "return add_referencia('" . urlencode($value->referencia) . "')";
                    if ($value->tipo == 'atributos') {
                        $value->funcion_js = "return get_combinaciones('" . urlencode($value->referencia) . "')";
                    }

                    $arttab[] = $value;
                }
            }

            $articulos[$tab] = $arttab;
            $num2 += $num;
        }

        return $articulos;
    }

    public function familias($todas = FALSE)
    {
        $familias = [];

        $familia = new familia();
        if ($todas) {
            $familias = $familia->all();
        } else if ($this->tpv_config['tpv_familias']) {
            $aux = explode(',', $this->tpv_config['tpv_familias']);
            if ($aux) {
                foreach ($familia->all() as $fam) {
                    if (in_array($fam->codfamilia, $aux)) {
                        $familias[] = $fam;
                    }
                }
            }
        }

        return $familias;
    }

    private function modificar_factura()
    {
        $fact0 = new factura_cliente();
        $factura = $fact0->get($_POST['idfactura']);
        if ($factura) {
            $articulo = new articulo();
            $asient0 = new asiento();

            /// paso 1, eliminamos el asiento asociado
            if (!is_null($factura->idasiento)) {
                $asiento = $asient0->get($factura->idasiento);
                if ($asiento) {
                    if ($asiento->delete()) {
                        $factura->idasiento = NULL;
                    }
                } else {
                    $factura->idasiento = NULL;
                }
            }

            /// paso 2, eliminamos las líneas
            foreach ($factura->get_lineas() as $linea) {
                $linea->delete();
            }

            /// paso 3, eliminar la líneas de IVA
            foreach ($factura->get_lineas_iva() as $liva) {
                $liva->delete();
            }

            /// paso 4, guardamos las nuevas
            $continuar = TRUE;
            $factura->neto = 0;
            $factura->totaliva = 0;
            $factura->totalirpf = 0;
            $factura->totalrecargo = 0;
            for ($i = 1; $i < 200; $i++) {
                if (isset($_POST['f_referencia_' . $i])) {
                    $art0 = $articulo->get($_POST['f_referencia_' . $i]);
                    if ($art0) {
                        $linea = new linea_factura_cliente();
                        $linea->idfactura = $factura->idfactura;
                        $linea->referencia = $art0->referencia;
                        $linea->descripcion = $_POST['f_desc_' . $i];
                        $linea->codimpuesto = $art0->codimpuesto;
                        $linea->iva = floatval($_POST['f_iva_' . $i]);
                        $linea->pvpunitario = floatval($_POST['f_pvp_' . $i]);
                        $linea->cantidad = floatval($_POST['f_cantidad_' . $i]);
                        $linea->pvpsindto = $linea->pvpunitario * $linea->cantidad;
                        $linea->pvptotal = $linea->pvpunitario * $linea->cantidad;

                        if (isset($_POST['f_codcombinacion_' . $i])) {
                            $linea->codcombinacion = $_POST['f_codcombinacion_' . $i];
                        }

                        if ($linea->save()) {
                            /// descontamos del stock
                            $art0->sum_stock($factura->codalmacen, 0 - $linea->cantidad, FALSE, $linea->codcombinacion);

                            $factura->neto += $linea->pvptotal;
                            $factura->totaliva += ($linea->pvptotal * $linea->iva / 100);
                        } else {
                            $this->new_error_msg("¡Imposible guardar la línea con referencia: " . $linea->referencia);
                            $continuar = FALSE;
                        }
                    } else {
                        $this->new_error_msg("Artículo no encontrado: " . $_POST['f_referencia_' . $i]);
                        $continuar = FALSE;
                    }
                }
            }

            if ($continuar) {
                /// redondeamos
                $factura->neto = round($factura->neto, FS_NF0);
                $factura->totaliva = round($factura->totaliva, FS_NF0);
                $factura->total = $factura->neto + $factura->totaliva;

                if ($factura->save()) {
                    $this->new_message('<a href="' . $factura->url() . '" class="text-capitalize">'
                        . FS_FACTURA . '</a> modificada correctamente.');
                } else {
                    $this->new_error_msg("Error al modificar la factura.");
                }
            } else {
                $this->new_error_msg("Error al modificar la factura.");
            }
        } else {
            $this->new_error_msg('Factura no encontrada.');
        }
    }
}
