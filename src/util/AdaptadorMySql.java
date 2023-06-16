/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Classes/Class.java to edit this template
 */
package util;

import java.sql.*;

/**
 *
 * @author SistemasRC0011
 */
public class AdaptadorMySql {

    static String DRIVER_MYSQL = "com.mysql.jdbc.Driver";

    //static String DRIVER_ACCESS = "net.ucanaccess.jdbc.UcanloadDriver";
    static String URL_MYSQL = "jdbc:mysql://localhost:3306/sld";
    //static String URL_ACCESS = "jdbc:ucanaccess:///media/javier/KINGSTON/Personal_2016/horas extras/horas_extras.accdb";
    // static String URL_ACCESS = "jdbc:ucanaccess://H:/per_2/horas extras/horas_extras.accdb";
    //static String URL_ACCESS = "jdbc:ucanaccess:///media/javier/KINGSTON/Personal_2016/horas extras/horas_extras.accdb";
    // static String URL_ACCESS = "jdbc:ucanaccess://d:/Personal_2016/horas extras/horas_extras.accdb";
    // static String URL_ACCESS = "jdbc:ucanaccess://d:/Personal_2016/horas extras/horas_extras.accdb";
    static String USER_ACCESS = "root";
    static String PASS_ACCESS = "admin";

    static String USER_MYSQL = "root";
    static String PASS_MYSQL = "admin";

    //  public static final String USER_BD_SEGURIDAD_MYSQL =  util.Global.USR_ADMINISTRADOR_DB;
    // public static final String PASSWORD_BD_SEGURIDAD_MYSQL =  "admin";
    /* private static final int CONECTAR_ACCESS = 0;
     private static final int CONECTAR_MYSQL  = 1;*/
    private Connection conexion;
    private Statement declaracion;
    public int Debug = 0;
    public ResultSet resultados;
    public boolean encontrado;

    public AdaptadorMySql() {
        this(URL_MYSQL, DRIVER_MYSQL, USER_MYSQL, PASS_MYSQL);
    }

    public AdaptadorMySql(String usuario, String password) {
        this(URL_MYSQL, DRIVER_MYSQL, usuario, password);
    }

    /* public AdaptadorJDBC(int baseDatos){
     this(URL_BD_SEGURIDAD_ACCESS, ODBC_DRIVER, USER_BD_SEGURIDAD_ACCESS, PASSWORD_BD_SEGURIDAD_ACCESS);
     }*/
    public AdaptadorMySql(String url, String driverName, String user, String passwd) {
        try {
            Class.forName(driverName);
            if (Debug != 0) {
                System.out.println("Abriendo la conexion a la BD");
            }
            conexion = DriverManager.getConnection(url, user, passwd);
            declaracion = conexion.createStatement(ResultSet.TYPE_SCROLL_SENSITIVE,
                    ResultSet.CONCUR_READ_ONLY);
        } catch (ClassNotFoundException ex) {
            System.err.println("No se pueden encontrar las clases de los driver de la Base de Datos.");
            System.err.println(ex);
        } catch (SQLException ex) {
            System.err.println("No se puede conectar a la Base de Datos.");
            System.err.println(ex);
        }
    }

    public ResultSet consultar(String consulta) {
        // System.err.println(consulta);
        if (conexion == null || declaracion == null) {
            System.err.println("No existe la Base de Datos en donde consultar.");
            return null;
        }
        try {
            if (Debug != 0) {
                System.out.println("SEL:" + consulta);
            }
            resultados = declaracion.executeQuery(consulta);
            if (resultados.next()) {
                encontrado = true;
            }
            resultados.beforeFirst();
        } catch (SQLException ex) {
            System.err.println(ex);
            return null;
        }
        return resultados;
    }

    public int actualizar(String psql) throws SQLException {
        if (conexion == null || declaracion == null) {
            System.err.println("No hay base de datos en donde ejecutar la actualizaci�n");
            return 0;
        }
        try {
            if (Debug != 0) {
                System.out.println("SQL:" + psql);
            }

        } catch (Exception ex) {
            //  ex.printStackTrace();
        }

        int count = declaracion.executeUpdate(psql);

        return count;

    }

    public void cerrar() throws SQLException {
        if (Debug != 0) {
            System.out.println("Cerrando la conexi�n a la BD");
        }
        if (!(resultados == null)) {
            resultados.close();
        }
        declaracion.close();
        conexion.close();
    }

    protected void finalizar() throws Throwable {
        cerrar();

    }

    public static void main(String[] args) {
        AdaptadorMySql ad = new AdaptadorMySql();
        try {
            
            String consulta = "SELECT cod_interno, concat(primer_apellido,', ', nombres) as empleado, mc.descripcion as modalidad "
                    + "from personal p "
                    + "join modalidad_contratacion mc on mc.id_modalidad = p.id_modalida_contratacion"
                    + " where activo = 'S'"
                    + ";";
            
            System.out.println(consulta);

            ad.consultar(consulta);

            while (ad.resultados.next()) {

                for (int i = 0; i < ad.resultados.getMetaData().getColumnCount(); i++) {
                    System.out.println(ad.resultados.getMetaData().getColumnName(i + 1).toUpperCase()
                            + " : " + ad.resultados.getString(i + 1).toUpperCase());
                }

                System.out.println("");
            }

            ResultSetMetaData meta = ad.resultados.getMetaData();
            int cols = meta.getColumnCount();

            for (int i = 0; i < cols; i++) {
                System.out.println(meta.getColumnTypeName(i + 1));

            }

        } catch (SQLException ex) {
            ex.printStackTrace();
        }

    }

}
