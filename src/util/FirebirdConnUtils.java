/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Classes/Class.java to edit this template
 */
package util;


import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.HashMap;
import java.util.Map;
import java.util.Properties;



/**
 *
 * @author SistemasRC0011
 */
public class FirebirdConnUtils {

    public static Connection getFirebirdConnection()
            throws ClassNotFoundException, SQLException {

        String hostName = "localhost";
        String dbName = "e:\\Vsueldos5\\db\\VSUELDOS.FDB";
        String userName = "sysdba";
        String password = "masterkey";
        return getFirebirdConnection(hostName, dbName, userName, password);
    }

    public static Connection getFirebirdConnection(String hostName, String dbName,
            String userName, String password) throws SQLException,
            ClassNotFoundException {

        // Declare the class Driver for MySQL DB
        // This is necessary with Java 5 (or older)
        // Java6 (or newer) automatically find the appropriate driver.
        // If you use Java> 5, then this line is not needed.
        Class.forName("org.firebirdsql.jdbc.FBDriver");

        // Cấu trúc URL Connection dành cho Oracle
        // Ví dụ: jdbc:mysql://localhost:3306/simplehr
        String connectionURL = "jdbc:firebirdsql://" + hostName + "/" + dbName;

        Properties conf = new Properties();

        conf.setProperty("userName", userName);
        conf.setProperty("password", password);
        conf.setProperty("charSet", "utf8");

        Connection conn = DriverManager.getConnection(connectionURL, conf);

        return conn;
    }

    public static HashMap<Integer, String[]> miniLeg() throws Exception {
        HashMap<Integer, String[]> salida = new HashMap<Integer, String[]>();

        Connection con = getFirebirdConnection();

        Statement stmt = con.createStatement();

        String consulta = "select id_liquidacion, periodo_liquidado from liquidaciones order by id_liquidacion desc";
        consulta = "select cod_interno, primer_apellido || ', ' || nombres, cedula_cuil, id_modalida_contratacion from personal p where activo = 'S' order by primer_apellido, nombres";

        ResultSet resultados = stmt.executeQuery(consulta);

        while (resultados.next()) {
            /*System.out.print(resultados.getString("id_liquidacion"));
                System.out.println(" - " + resultados.getString("periodo_liquidado"));*/
            String[] temp = new String[]{
                resultados.getString(2),
                resultados.getString(3),
                resultados.getString(4)};

            salida.put(resultados.getInt(1), temp);

        }
        con.close();

        return salida;
    }

    public static void main(String[] args) throws Exception {
        Connection con = getFirebirdConnection();

        Statement stmt = con.createStatement();

        String consulta = "select id_liquidacion, periodo_liquidado from liquidaciones order by id_liquidacion desc";
        consulta = "select cod_interno, primer_apellido || ', ' || nombres, cedula_cuil, id_modalida_contratacion from personal p where activo = 'S' order by primer_apellido, nombres";

        ResultSet resultados = stmt.executeQuery(consulta);

        while (resultados.next()) {
            /*System.out.print(resultados.getString("id_liquidacion"));
                System.out.println(" - " + resultados.getString("periodo_liquidado"));*/

            System.out.print(resultados.getString(1) + "\t");
            System.out.print(resultados.getString(2) + "\t");
            System.out.print(resultados.getString(3) + "\t");
            System.out.print(resultados.getString(4) + "\t");
            System.out.println("");

        }

        consulta = "SELECT * FROM PERSONAL";

        resultados = stmt.executeQuery(consulta);
        resultados.next();
        System.out.println(resultados.getDouble(1));

        System.out.println(miniLeg().get(1056)[1]);
    }

}
