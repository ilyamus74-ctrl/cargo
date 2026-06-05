#include <boost/asio.hpp>
#include <boost/beast.hpp>
#include <boost/beast/websocket.hpp>
#include <iostream>
#include <string>
#include <termios.h>
#include <fcntl.h>
#include <unistd.h>

namespace asio = boost::asio;
namespace beast = boost::beast;
namespace http = beast::http;
namespace ws = beast::websocket;
using tcp = asio::ip::tcp;

static int open_uart(const char* dev, int baud) {
    int fd = ::open(dev, O_RDWR | O_NOCTTY | O_SYNC);
    if (fd < 0) { perror("open uart"); return -1; }

    termios tty{};
    if (tcgetattr(fd, &tty) != 0) { perror("tcgetattr"); ::close(fd); return -1; }

    cfmakeraw(&tty);
    tty.c_cflag |= (CLOCAL | CREAD);
    tty.c_cflag &= ~CRTSCTS;
    tty.c_cflag &= ~CSTOPB;
    tty.c_cflag &= ~PARENB;
    tty.c_cflag &= ~CSIZE;
    tty.c_cflag |= CS8;

    speed_t sp = B115200;
    if (baud == 9600) sp = B9600;
    else if (baud == 19200) sp = B19200;
    else if (baud == 38400) sp = B38400;
    else if (baud == 57600) sp = B57600;
    else if (baud == 115200) sp = B115200;

    cfsetispeed(&tty, sp);
    cfsetospeed(&tty, sp);

    tty.c_cc[VMIN]  = 0;
    tty.c_cc[VTIME] = 1; // 100ms

    if (tcsetattr(fd, TCSANOW, &tty) != 0) { perror("tcsetattr"); ::close(fd); return -1; }
    return fd;
}

static bool uart_write_line(int fd, std::string s) {
    if (s.empty()) return true;
    if (s.back() != '\n') s.push_back('\n');

    const char* p = s.data();
    size_t left = s.size();
    while (left > 0) {
        ssize_t n = ::write(fd, p, left);
        if (n < 0) { perror("uart write"); return false; }
        left -= (size_t)n;
        p += n;
    }
    return true;
}

struct session {
    ws::stream<tcp::socket> wss;
    beast::flat_buffer buf;
    int uart_fd;

    session(tcp::socket sock, int uart_fd_) : wss(std::move(sock)), uart_fd(uart_fd_) {}

    void run() {
        beast::flat_buffer buffer;
        http::request<http::string_body> req;

        // читаем HTTP request (WS handshake тоже HTTP)
        http::read(wss.next_layer(), buffer, req);

        // принимаем только /ws + upgrade
        if (!ws::is_upgrade(req) || req.target() != "/ws") {
            http::response<http::string_body> res{http::status::not_found, req.version()};
            res.set(http::field::server, "ws-uart-bridge");
            res.set(http::field::content_type, "text/plain");
            res.body() = "WebSocket endpoint: ws://<host>:8765/ws\n";
            res.prepare_payload();
            http::write(wss.next_layer(), res);
            return;
        }

        // WS accept (ОДИН раз)
        wss.set_option(ws::stream_base::timeout::suggested(beast::role_type::server));
        wss.accept(req);

        // основной цикл
        for (;;) {
            buf.clear();
            beast::error_code ec;
            wss.read(buf, ec);

            if (ec == ws::error::closed) break;       // клиент закрыл
            if (ec) throw beast::system_error(ec);    // прочие ошибки

            auto msg = beast::buffers_to_string(buf.data());

            if (msg.size() > 256) msg.resize(256);

            std::cerr << "WS->UART: " << msg << "\n";
            if (!uart_write_line(uart_fd, msg)) {
                beast::error_code ec2;
                wss.write(asio::buffer(std::string("ERR: uart write failed\n")), ec2);
                break;
            }

            // ACK (можешь убрать, если не надо)
            beast::error_code ec3;
            wss.write(asio::buffer(std::string("OK\n")), ec3);
        }

        // грейсфул close (чтобы не ловить assert на reset)
        beast::error_code ec;
        wss.close(ws::close_code::normal, ec);
    }
};

int main(int argc, char** argv) {
    const char* uart_dev = (argc > 1) ? argv[1] : "/dev/ttyUSB0";
    int baud = (argc > 2) ? std::stoi(argv[2]) : 115200;
    int port = (argc > 3) ? std::stoi(argv[3]) : 8765;

    int uart_fd = open_uart(uart_dev, baud);
    if (uart_fd < 0) return 1;

    try {
        asio::io_context ioc(1);
        tcp::acceptor acc(ioc, {tcp::v4(), (unsigned short)port});

        std::cerr << "WS UART bridge listening on 0.0.0.0:" << port
                  << " -> " << uart_dev << "@" << baud << "\n";

        for (;;) {
            tcp::socket sock(ioc);
            acc.accept(sock);
            std::cerr << "client connected\n";
            try {
                session(std::move(sock), uart_fd).run();
            } catch (const std::exception& e) {
                std::cerr << "session error: " << e.what() << "\n";
            }
            std::cerr << "client disconnected\n";
        }
    } catch (const std::exception& e) {
        std::cerr << "fatal: " << e.what() << "\n";
        ::close(uart_fd);
        return 1;
    }
}

