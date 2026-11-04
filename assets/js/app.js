/* =====================================================================
   Govinda Education — Interaksi global
   ===================================================================== */
(function () {
    'use strict';

    const onReady = (fn) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    };

    const formatRupiah = (value) => {
        const number = Number(value || 0);
        return 'Rp' + number.toLocaleString('id-ID', { maximumFractionDigits: 0 });
    };
    window.geFormatRupiah = formatRupiah;

    onReady(() => {
        // -------------------------------------------------------------
        // Tooltip Bootstrap
        // -------------------------------------------------------------
        if (window.bootstrap && bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                new bootstrap.Tooltip(el);
            });
        }

        // -------------------------------------------------------------
        // Tampilkan / sembunyikan password
        // -------------------------------------------------------------
        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-target');
                const input = targetId ? document.getElementById(targetId) : null;
                if (!input) {
                    return;
                }
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                const icon = button.querySelector('i');
                if (icon) {
                    icon.classList.toggle('bi-eye', !show);
                    icon.classList.toggle('bi-eye-slash', show);
                }
                button.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
                input.focus();
            });
        });

        // -------------------------------------------------------------
        // Salin tautan / teks ke clipboard
        // -------------------------------------------------------------
        document.querySelectorAll('[data-copy-target], [data-copy]').forEach((button) => {
            button.addEventListener('click', () => {
                const selector = button.getAttribute('data-copy-target');
                const target = selector ? document.querySelector(selector) : null;
                const value = target
                    ? ('value' in target ? target.value : target.textContent)
                    : (button.getAttribute('data-copy') || '');

                if (!value) {
                    return;
                }

                const original = button.innerHTML;
                const done = () => {
                    button.innerHTML = '<i class="bi bi-check2"></i>';
                    button.classList.add('is-copied');
                    window.setTimeout(() => {
                        button.innerHTML = original;
                        button.classList.remove('is-copied');
                    }, 1800);
                };

                const fallbackCopy = () => {
                    const helper = document.createElement('textarea');
                    helper.value = value;
                    helper.setAttribute('readonly', 'readonly');
                    helper.style.position = 'fixed';
                    helper.style.left = '-9999px';
                    document.body.appendChild(helper);
                    helper.select();
                    try {
                        document.execCommand('copy');
                        done();
                    } catch (error) {
                        // Diamkan: browser menolak akses clipboard.
                    }
                    document.body.removeChild(helper);
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(value).then(done).catch(fallbackCopy);
                } else {
                    fallbackCopy();
                }
            });
        });

        // -------------------------------------------------------------
        // Pratinjau foto sebelum upload
        // -------------------------------------------------------------
        document.querySelectorAll('[data-photo-input]').forEach((input) => {
            const previewSelector = input.getAttribute('data-photo-preview');
            const preview = previewSelector ? document.querySelector(previewSelector) : null;

            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                input.setCustomValidity('');

                if (!file) {
                    return;
                }
                if (['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) === -1) {
                    input.value = '';
                    input.setCustomValidity('Format foto harus JPG, JPEG, PNG, atau WEBP.');
                    input.reportValidity();
                    return;
                }
                if (file.size > 2 * 1024 * 1024) {
                    input.value = '';
                    input.setCustomValidity('Ukuran foto maksimal 2 MB.');
                    input.reportValidity();
                    return;
                }
                if (!preview || !window.FileReader) {
                    return;
                }
                const reader = new FileReader();
                reader.onload = (event) => {
                    if (typeof event.target.result === 'string') {
                        preview.src = event.target.result;
                    }
                };
                reader.readAsDataURL(file);
            });
        });

        // -------------------------------------------------------------
        // Konfirmasi tindakan penting (SweetAlert2)
        // -------------------------------------------------------------
        const askConfirmation = (element) => {
            const title = element.getAttribute('data-confirm-title') || 'Konfirmasi tindakan';
            const text = element.getAttribute('data-confirm')
                || 'Pastikan data sudah benar. Tindakan ini akan diproses.';
            const confirmText = element.getAttribute('data-confirm-button') || 'Ya, lanjutkan';

            if (!window.Swal) {
                return Promise.resolve(window.confirm(text));
            }

            return Swal.fire({
                title: title,
                text: text,
                icon: element.getAttribute('data-confirm-icon') || 'question',
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: 'Batal',
                confirmButtonColor: element.getAttribute('data-confirm-color') || '#0f766e',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
                focusCancel: true,
            }).then((result) => result.isConfirmed);
        };

        document.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === '1') {
                    return;
                }
                event.preventDefault();
                askConfirmation(form).then((confirmed) => {
                    if (!confirmed) {
                        return;
                    }
                    form.dataset.confirmed = '1';
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                });
            });
        });

        document.querySelectorAll('a[data-confirm]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                askConfirmation(link).then((confirmed) => {
                    if (confirmed) {
                        window.location.href = link.getAttribute('href');
                    }
                });
            });
        });

        // -------------------------------------------------------------
        // Cegah submit ganda
        // -------------------------------------------------------------
        document.querySelectorAll('form[data-submit-once]').forEach((form) => {
            form.addEventListener('submit', () => {
                if (form.hasAttribute('data-confirm') && form.dataset.confirmed !== '1') {
                    return;
                }
                if (form.dataset.submitted === '1' || !form.checkValidity()) {
                    return;
                }
                form.dataset.submitted = '1';
                const button = form.querySelector('[type="submit"]');
                if (button) {
                    button.disabled = true;
                    const original = button.innerHTML;
                    button.dataset.originalHtml = original;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Memproses...';
                }
            });
        });

        // -------------------------------------------------------------
        // Kirim otomatis saat pilihan filter berubah
        // -------------------------------------------------------------
        document.querySelectorAll('[data-auto-submit]').forEach((field) => {
            field.addEventListener('change', () => {
                const form = field.form;
                if (!form) {
                    return;
                }
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        });

        // -------------------------------------------------------------
        // Tutup otomatis alert sukses
        // -------------------------------------------------------------
        document.querySelectorAll('.app-alert.alert-success').forEach((alert) => {
            window.setTimeout(() => {
                if (window.bootstrap && bootstrap.Alert) {
                    const instance = bootstrap.Alert.getOrCreateInstance(alert);
                    instance.close();
                }
            }, 6000);
        });

        // -------------------------------------------------------------
        // Select2 (jika tersedia di halaman)
        // -------------------------------------------------------------
        if (window.jQuery && jQuery.fn.select2) {
            jQuery('.js-select2').each(function () {
                const el = jQuery(this);
                el.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: el.data('placeholder') || 'Pilih…',
                    allowClear: el.data('allow-clear') !== false,
                    dropdownParent: el.closest('.modal').length ? el.closest('.modal') : jQuery(document.body),
                });
            });
        }

        // -------------------------------------------------------------
        // DataTables (jika tersedia di halaman)
        // -------------------------------------------------------------
        if (window.DataTable) {
            const language = {
                search: 'Cari:',
                searchPlaceholder: 'Ketik untuk mencari…',
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data',
                infoFiltered: '(difilter dari _MAX_ total data)',
                zeroRecords: 'Data tidak ditemukan',
                emptyTable: 'Belum ada data',
                paginate: {
                    first: 'Awal',
                    last: 'Akhir',
                    next: 'Berikutnya',
                    previous: 'Sebelumnya',
                },
            };

            document.querySelectorAll('table.js-datatable').forEach((table) => {
                new DataTable(table, {
                    language: language,
                    pageLength: 10,
                    lengthMenu: [10, 25, 50, 100],
                    order: [],
                    columnDefs: [
                        { targets: 'no-sort', orderable: false, searchable: false },
                    ],
                    autoWidth: false,
                });
            });
        }
    });
})();
