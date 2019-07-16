<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Setup class
 *
 * @package   e-Learning Dokumenary Net
 * @author    Almazari <almazary@gmail.com>
 * @copyright Copyright (c) 2013 - 2016, Dokumenary Net.
 * @since     1.0
 * @link      http://dokumenary.net
 *
 * INDEMNITY
 * You agree to indemnify and hold harmless the authors of the Software and
 * any contributors for any direct, indirect, incidental, or consequential
 * third-party claims, actions or suits, as well as any related expenses,
 * liabilities, damages, settlements or fees arising from your use or misuse
 * of the Software, or a violation of any terms of this license.
 *
 * DISCLAIMER OF WARRANTY
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESSED OR
 * IMPLIED, INCLUDING, BUT NOT LIMITED TO, WARRANTIES OF QUALITY, PERFORMANCE,
 * NON-INFRINGEMENT, MERCHANTABILITY, OR FITNESS FOR A PARTICULAR PURPOSE.
 *
 * LIMITATIONS OF LIABILITY
 * YOU ASSUME ALL RISK ASSOCIATED WITH THE INSTALLATION AND USE OF THE SOFTWARE.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS OF THE SOFTWARE BE LIABLE
 * FOR CLAIMS, DAMAGES OR OTHER LIABILITY ARISING FROM, OUT OF, OR IN CONNECTION
 * WITH THE SOFTWARE. LICENSE HOLDERS ARE SOLELY RESPONSIBLE FOR DETERMINING THE
 * APPROPRIATENESS OF USE AND ASSUME ALL RISKS ASSOCIATED WITH ITS USE, INCLUDING
 * BUT NOT LIMITED TO THE RISKS OF PROGRAM ERRORS, DAMAGE TO EQUIPMENT, LOSS OF
 * DATA OR SOFTWARE PROGRAMS, OR UNAVAILABILITY OR INTERRUPTION OF OPERATIONS.
 */


class Setup extends CI_Controller
{
    private $db_error;
    private $prefix;

    function __construct()
    {
        parent::__construct();

        # load helper
        $this->load->helper(array('url', 'form', 'text', 'elearning', 'security', 'file', 'number', 'date'));

        # load library
        $this->load->library(array('form_validation', 'twig', 'user_agent'));

        # delimiters form validation
        $this->form_validation->set_error_delimiters('<span class="text-error"><i class="icon-info-sign"></i> ', '</span>');

        try {
            $success = install_success();
            if ($success) {
                redirect('login');
            }
        } catch (Exception $e) {
            $this->db_error = $e->getMessage();
        }

        if (empty($this->db_error)) {
            $this->load->database();

            include APPPATH . 'config/database.php';

            $this->prefix = $db['default']['dbprefix'];

            # load model
            $this->load->model(array('config_model', 'kelas_model', 'login_model', 'mapel_model', 'materi_model', 'pengajar_model', 'siswa_model', 'tugas_model'));

            # load session
            $this->load->library('session');
        }
    }

    function index($step = '')
    {
        switch ($step) {
            case '4':
                if (!empty($this->db_error)) {
                    redirect('setup/index/1');
                }

                $check = $this->config_model->retrieve('nama-sekolah');
                if (empty($check)) {
                    redirect('setup/index/2');
                }

                # cek kelas
                $check = $this->db->count_all_results('kelas');
                if (empty($check)) {
                    redirect('setup/index/3');
                }

                if ($this->form_validation->run('register/pengajar') == true) {
                    $nip           = $this->input->post('nip', TRUE);
                    $nama          = $this->input->post('nama', TRUE);
                    $jenis_kelamin = $this->input->post('jenis_kelamin', TRUE);
                    $tempat_lahir  = $this->input->post('tempat_lahir', TRUE);
                    $tgl_lahir     = $this->input->post('tgl_lahir', TRUE);
                    $bln_lahir     = $this->input->post('bln_lahir', TRUE);
                    $thn_lahir     = $this->input->post('thn_lahir', TRUE);
                    $alamat        = $this->input->post('alamat', TRUE);
                    $username      = $this->input->post('username', TRUE);
                    $password      = $this->input->post('password2', TRUE);
                    $is_admin      = 1;
                    $foto          = null;

                    if (empty($thn_lahir)) {
                        $tanggal_lahir = null;
                    } else {
                        $tanggal_lahir = $thn_lahir.'-'.$bln_lahir.'-'.$tgl_lahir;
                    }

                    # simpan data siswa
                    $pengajar_id = $this->pengajar_model->create(
                        $nip,
                        $nama,
                        $jenis_kelamin,
                        $tempat_lahir,
                        $tanggal_lahir,
                        $alamat,
                        $foto,
                        1
                    );

                    # simpan data login
                    $this->login_model->create(
                        $username,
                        $password,
                        null,
                        $pengajar_id,
                        $is_admin
                    );

                    # create success install
                    $this->config_model->create('install-success', 'install-success', '1');

                    $this->session->set_flashdata('login', get_alert('success', 'Instalasi E-learning berhasil, silakan login sebagai Administrator.'));
                    redirect('login');
                }

                # cek admin
                $this->db->where('is_admin', 1);
                $result = $this->db->get('login');
                $result = $result->row_array();

                $data['success'] = false;
                if (!empty($result)) {
                    $data['success'] = true;
                }

                $this->twig->display('install-step-4.html', $data);
            break;

            case '3':
                if (!empty($this->db_error)) {
                    redirect('setup/index/1');
                }

                $check = $this->config_model->retrieve('nama-sekolah');
                if (empty($check)) {
                    redirect('setup/index/2');
                }

                # cek kelas
                $check = $this->db->count_all_results('kelas');
                if (!empty($check)) {
                    redirect('setup/index/4');
                }

                if (!empty($_POST)) {
                    # simpan kelas
                    foreach ($_POST['kelas'] as $key => $val) {
                        # cek parent sudah ada belum
                        $this->db->where('nama', "KELAS $key");
                        $this->db->where('parent_id', null);
                        $result = $this->db->get('kelas');
                        $parent = $result->row_array();
                        if (empty($parent)) {
                            $parent_id = $this->kelas_model->create("KELAS $key", null);
                        } else {
                            $parent_id = $parent['id'];
                        }

                        # simpan child
                        foreach ($val as $child) {
                            $this->db->where('nama', "KELAS $key - $child");
                            $this->db->where('parent_id', $parent_id);
                            $result = $this->db->get('kelas');
                            $result = $result->row_array();
                            if (empty($result)) {
                                $this->kelas_model->create("KELAS $key - $child", $parent_id);
                            }
                        }
                    }

                    # simpan mapel
                    foreach ($_POST['mapel'] as $nama) {
                        # cek mapel
                        $this->db->where('nama', $nama);
                        $result = $this->db->get('mapel');
                        $result = $result->row_array();
                        if (empty($result)) {
                            $this->mapel_model->create($nama);
                        }
                    }

                    redirect('setup/index/4');
                }

                $data['jenjang'] = get_pengaturan('jenjang', 'value');

                $this->twig->display('install-step-3.html', $data);
            break;

            case '2':
                if (!empty($this->db_error)) {
                    redirect('setup/index/1');
                }

                $check = $this->config_model->retrieve('nama-sekolah');
                if (!empty($check)) {
                    redirect('setup/index/3');
                }

                if ($this->form_validation->run('setup/index/2') == true) {
                    foreach ($_POST as $key => $val) {
                        $this->config_model->create(
                            $key,
                            $key,
                            $val
                        );
                    }

                    redirect('setup/index/3');
                }

                $this->twig->display('install-step-2.html');
            break;

            case '1':
            default:
                if (empty($this->db_error)) {
                    # cek tabel pengaturan, jika sudah ada lanjut ke step 2
                    if ($this->db->table_exists('pengaturan')) {
                        redirect('setup/index/2');
                    }

                    $this->db->trans_start();

                    $this->config_model->create_default_table("all");

                    $data_pengaturan = array(
                        array(
                            'id'    => 'email-server',
                            'nama'  => 'Email server',
                            'value' => 'no-reply@domain.com',
                        ),
                        array(
                            'id'    => 'email-template-approve-pengajar',
                            'nama'  => 'Approve Pengajar (Email Pengajar)',
                            'value' => '{"subject":"Akun Anda Sudah di Aktifkan...","body":"<p>Hallo {$nama},<\\/p>\\n<p>Akun Anda sebagai <em><strong>Pengajar</strong></em> di E-Learning {$nama_sekolah} telah kami <em><strong>Atifkan</strong></em>...<\\/p>\\n<p>Berikut data informasi akun Anda:<\\/p>\\n<p>{$tabel_profil}<\\/p>\\n<p>Silahkan Login di : {$url_login}<\\/p>\\n<p>Masukkan <em><strong>Username</strong></em> dan <em><strong>Password</strong></em> yang telah anda buat saat pendaftaran untuk masuk ke sistem E-Learning {$nama_sekolah}.<\\/p>\\n<p>&nbsp;<\\/p>\\n<p>Regards, Administrator<\\/p>"}'
                        ),
                        array(
                            'id'    => 'email-template-approve-siswa',
                            'nama'  => 'Approve Siswa (Email Siswa)',
                            'value' => '{"subject":"Akun Anda Sudah di Aktifkan...","body":"<p>Hallo {$nama},<\\/p>\\n<p>Akun Anda sebagai <em><strong>Pengajar</strong></em> di E-Learning {$nama_sekolah} telah kami <em><strong>Atifkan</strong></em>...<\\/p>\\n<p>Berikut data informasi akun Anda:<\\/p>\\n<p>{$tabel_profil}<\\/p>\\n<p>Silahkan Login di : {$url_login}<\\/p>\\n<p>Masukkan <em><strong>Username</strong></em> dan <em><strong>Password</strong></em> yang telah anda buat saat pendaftaran untuk masuk ke sistem E-Learning {$nama_sekolah}.<\\/p>\\n<p>&nbsp;<\\/p>\\n<p>Regards, Administrator<\\/p>"}'
                        ),
                        array(
                            'id'    => 'email-template-link-reset',
                            'nama'  => 'Link Reset Password',
                            'value' => '{"subject":"Reset Password Akun...","body":"<p>Hallo,<\\/p>\\n<p>Anda mengirimkan permintaan untuk reset password Anda, silahkan klik link berikut untuk reset password : {$link_reset}<\\/p>\\n<p>&nbsp;<\\/p>\\n<p>Regards, Administrator<\\/p>"}'
                        ),
                        array(
                            'id'    => 'email-template-register-pengajar',
                            'nama'  => 'Pendaftaran Pengajar (Email Pengajar)',
                            'value' => '{"subject":"Pendaftaran Akun Berhasil...","body":"<p>Hallo {$nama},<\\/p>\\n<p>Terimakasih telah melakukan pendaftaran sebagai <strong><em>Pengajar</em></strong> di E-Learning {$nama_sekolah}. Mohon menunggu, akun Anda akan segera diaktifkan.<\\/p>\\n<p>Regards, Administrator<\\/p>"}'
                        ),
                        array(
                            'id'    => 'email-template-register-siswa',
                            'nama'  => 'Pendaftaran Siswa (Email Siswa)',
                            'value' => '{"subject":"Pendaftaran Akun Berhasil...","body":"<p>Hallo {$nama},<\\/p>\\n<p>Terimakasih telah melakukan pendaftaran sebagai <strong><em>Siswa</em></strong> di E-Learning {$nama_sekolah}. Mohon menunggu, akun Anda akan segera diaktifkan.<\\/p>\\n<p>Regards, Administrator<\\/p>"}'
                        ),
                        array(
                            'id'    => 'info-registrasi',
                            'nama'  => 'Info Pendaftaran',
                            'value' => '<p>Silahkan mengisi formulir registrasi E-Learning dengan memilih tab Siswa jika mendaftar sebagai siswa (peserta didik), atau tab Pengajar jika mendaftar sebagai guru (pengajar). Isikan data diri Anda dengan Lengkap &amp; Benar. Jika ditemukan data yang tidak sesuai maka akan diberikan sanksi.</p>'
                        ),
                        array(
                            'id'    => 'peraturan-elearning',
                            'nama'  => 'Peraturan E-learning',
                            'value' => ''
                        ),
                        array(
                            'id'    => 'registrasi-pengajar',
                            'nama'  => 'Pendaftaran Pengajar',
                            'value' => '1'
                        ),
                        array(
                            'id'    => 'registrasi-siswa',
                            'nama'  => 'Pendaftaran Siswa',
                            'value' => '1'
                        ),
                        array(
                            'id'    => 'versi',
                            'nama'  => 'Versi',
                            'value' => '1.8'
                        ),
                    );
                    $this->db->insert_batch('pengaturan', $data_pengaturan);

                    $this->db->trans_complete();

                    redirect('setup/index/2');
                }

                $set_base_url = explode('index.php', current_url());
                $data['set_base_url'] = $set_base_url[0];

                $ambil_error = "";
                if (!empty($this->db_error)) {
                    $ambil_error = get_alert('error', $this->db_error);
                }
                $data['error'] = $ambil_error;
                $this->twig->display('install-step-1.html', $data);
            break;
        }
    }
}
